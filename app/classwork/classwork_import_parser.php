<?php
require_once __DIR__ . '/classwork_support.php';

// Import sources are read only from PHP's upload temporary file, never published or retained.
function kns_import_source(array $upload): array
{
    if (!isset($upload['error'], $upload['name'], $upload['tmp_name'])
        || !is_int($upload['error']) || !is_string($upload['name']) || !is_string($upload['tmp_name'])) {
        throw new InvalidArgumentException('Choose one DOCX, TXT, CSV, or RTF file.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The file could not be uploaded. Choose a nonempty file no larger than 5 MiB.');
    }
    $path = $upload['tmp_name'];
    if (!is_uploaded_file($path) || !($size = filesize($path)) || $size > KNS_SUBJECT_MAX_FILE_BYTES) {
        throw new InvalidArgumentException('Choose a nonempty file no larger than 5 MiB.');
    }
    $name = basename(str_replace('\\', '/', $upload['name']));
    kns_classwork_text($name, 180, 'Filename');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $notices = [];
    if ($extension === 'docx') {
        $type = kns_subject_file_types()['docx'];
        if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
            throw new InvalidArgumentException('DOCX reading is unavailable on this server. Export to TXT or CSV, or edit questions manually.');
        }
        if (!in_array($mime, [$type['mime'], 'application/zip', 'application/x-zip', 'application/x-zip-compressed'], true)
            || !kns_subject_office_valid($path, $type)) {
            throw new InvalidArgumentException('This is not a readable DOCX document, or it is encrypted, contains macros, or exceeds archive limits.');
        }
        $text = kns_import_docx($path, $notices);
    } elseif (in_array($extension, ['txt', 'csv', 'rtf'], true)) {
        $allowed = $extension === 'rtf'
            ? ['text/rtf', 'application/rtf', 'text/plain']
            : ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'text/tab-separated-values', 'application/octet-stream'];
        if (!in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('The file content does not match its extension. Export plain text, CSV, or RTF and try again.');
        }
        $text = file_get_contents($path);
        if ($extension === 'rtf') {
            $text = kns_import_rtf($text, $notices);
        } else {
            $text = kns_import_utf8($text);
        }
    } else {
        throw new InvalidArgumentException('Use DOCX, TXT, CSV, or RTF. PDF, scans, images, and legacy DOC files are not supported; export their questions to a supported format.');
    }
    if (strlen($text) > KNS_CLASSWORK_MAX_TEXT_BYTES) {
        throw new InvalidArgumentException('The extracted text exceeds 1 MiB. Split the document into smaller files.');
    }
    $text = kns_import_utf8($text);
    if (trim($text) === '') {
        throw new InvalidArgumentException('No readable text was found. Scans and image-only questions cannot be imported; enter them manually.');
    }
    $result = $extension === 'csv' ? kns_import_csv($text) : kns_import_questions($text);
    $result['notices'] = array_values(array_unique(array_merge($notices, $result['notices'])));
    $result['source_text'] = $text;
    $result['file'] = ['name' => $name, 'type' => strtoupper($extension), 'size' => $size];
    $result['choice_count'] = array_sum(array_map(static fn($q) => count($q['choices']), $result['questions']));
    return $result;
}

function kns_import_utf8(string $text): string
{
    if (strncmp($text, "\xFF\xFE", 2) === 0 || strncmp($text, "\xFE\xFF", 2) === 0) {
        $encoding = strncmp($text, "\xFF\xFE", 2) === 0 ? 'UTF-16LE' : 'UTF-16BE';
        if (!mb_check_encoding(substr($text, 2), $encoding)) {
            throw new InvalidArgumentException('The source contains malformed UTF-16 text. Export it as valid UTF-8 to preserve the content.');
        }
        $text = mb_convert_encoding(substr($text, 2), 'UTF-8', $encoding);
    }
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
        throw new InvalidArgumentException('The document contains binary or unsupported text. Export as UTF-8 TXT/CSV, DOCX, or RTF.');
    }
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function kns_import_xml(string $xml): DOMDocument
{
    if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
        throw new InvalidArgumentException('The DOCX contains unsupported XML declarations.');
    }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new InvalidArgumentException('The DOCX text could not be read. Export it to TXT or CSV.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    return $document;
}

function kns_import_docx(string $path, array &$notices): string
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new InvalidArgumentException('The DOCX archive could not be opened.');
    }
    try {
        $part = $zip->statName('word/document.xml');
        if (!$part || $part['size'] > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('The DOCX document part is missing or too large.');
        }
        $document = kns_import_xml($zip->getFromName('word/document.xml'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $numbering = [];
        $numberingPart = $zip->statName('word/numbering.xml');
        if ($numberingPart && $numberingPart['size'] <= 1024 * 1024) {
            $numberDocument = kns_import_xml($zip->getFromName('word/numbering.xml'));
            $nx = new DOMXPath($numberDocument);
            $nx->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ($nx->query('//w:num') as $num) {
                $numId = $num->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                $abstractId = $nx->evaluate('string(w:abstractNumId/@w:val)', $num);
                foreach ($nx->query('//w:abstractNum') as $abstract) {
                    if ($abstract->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'abstractNumId') !== $abstractId) continue;
                    foreach ($nx->query('w:lvl', $abstract) as $level) {
                        $levelId = $level->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl');
                        $numbering[$numId][$levelId] = [
                            'format' => $nx->evaluate('string(w:numFmt/@w:val)', $level),
                            'start' => max(1, (int) $nx->evaluate('string(w:start/@w:val)', $level)),
                        ];
                    }
                }
                foreach ($nx->query('w:lvlOverride', $num) as $override) {
                    $levelId = $override->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl');
                    $start = $nx->evaluate('string(w:startOverride/@w:val)', $override);
                    if ($nx->query('w:lvl', $override)->length || !ctype_digit($start) || (int) $start < 1
                        || !isset($numbering[$numId][$levelId])) {
                        unset($numbering[$numId][$levelId]);
                        $notices[] = 'A custom Word numbering override could not be read. Check its missing labels in the original.';
                    } else {
                        $numbering[$numId][$levelId]['start'] = (int) $start;
                    }
                }
            }
        }
        $lines = [];
        $counters = [];
        foreach ($xpath->query('//w:body//w:p[not(ancestor::w:txbxContent)]') as $paragraph) {
            $line = '';
            foreach ($xpath->query('.//w:t[not(ancestor::w:del) and not(ancestor::w:txbxContent)] | .//w:tab[not(ancestor::w:txbxContent)] | .//w:br[not(ancestor::w:txbxContent)]', $paragraph) as $node) {
                $line .= $node->localName === 't' ? $node->textContent : ($node->localName === 'tab' ? "\t" : "\n");
            }
            $numId = $xpath->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $paragraph);
            $level = $xpath->evaluate('string(w:pPr/w:numPr/w:ilvl/@w:val)', $paragraph);
            $level = $level === '' ? '0' : $level;
            if ($numId !== '' && $numId !== '0' && trim($line) !== '') {
                $style = $numbering[$numId][$level] ?? null;
                if ($style && in_array($style['format'], ['decimal', 'upperLetter', 'lowerLetter'], true)) {
                    $counter = ($counters[$numId][$level] ?? ($style['start'] - 1)) + 1;
                    $counters[$numId][$level] = $counter;
                    foreach (array_keys($counters[$numId]) as $other) {
                        if ((int) $other > (int) $level) unset($counters[$numId][$other]);
                    }
                    if ($style['format'] === 'decimal') $line = $counter . '. ' . $line;
                    elseif ($counter <= 26) $line = chr(64 + $counter) . ') ' . $line;
                    else $notices[] = 'A Word answer list exceeds 26 entries and needs manual labels.';
                } else {
                    $notices[] = 'Some Word list labels were unsupported; check the extracted text and question boundaries.';
                }
            }
            if (trim($line) !== '') $lines[] = $line;
        }
        // Imported body text is visible for review; non-body/visual content is explicitly excluded.
        $notices[] = 'DOCX imports body text and simple numbered lists. Check tables, list numbering, and layout against the original.';
        if ($xpath->query('//w:drawing | //w:pict | //w:object | //w:txbxContent | //w:altChunk | //w:del | //w:ins | //*[local-name()="oMath"]')->length) {
            $notices[] = 'Images, objects, text boxes, equations, or tracked changes were found. They cannot be reproduced reliably; review the original and complete affected questions manually.';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('~^word/(header|footer|footnotes|endnotes)~', $zip->getNameIndex($i))) {
                $notices[] = 'Headers, footers, footnotes, and endnotes are excluded. Check the original for any question content there.';
                break;
            }
        }
        return implode("\n", $lines);
    } finally {
        $zip->close();
    }
}

function kns_import_rtf(string $source, array &$notices): string
{
    if (!preg_match('/^\{\\\\rtf1\b/', $source)) {
        throw new InvalidArgumentException('The RTF content does not match its file extension.');
    }
    if (preg_match('/\\\\ansicpg(\d+)/', $source, $match) && $match[1] !== '1252') {
        throw new InvalidArgumentException('This RTF uses an unsupported code page. Export it to UTF-8 TXT or DOCX.');
    }
    if (preg_match('/\\\\(?:mac|pc|pca)\b/', $source)) {
        throw new InvalidArgumentException('This RTF encoding is unsupported. Export it to UTF-8 TXT or DOCX.');
    }
    $notices[] = 'RTF imports plain body text only. Formatting, headers, images, objects, and equations need comparison with the original.';
    $stack = [];
    $state = ['skip' => false, 'uc' => 1];
    $fallback = 0;
    $out = '';
    $length = strlen($source);
    for ($i = 0; $i < $length;) {
        $char = $source[$i++];
        if ($char === '{') {
            if (count($stack) >= 100) throw new InvalidArgumentException('The RTF nesting is too complex.');
            $stack[] = $state;
            continue;
        }
        if ($char === '}') {
            if (!$stack) throw new InvalidArgumentException('The RTF groups are invalid.');
            $state = array_pop($stack);
            continue;
        }
        if ($char !== '\\') {
            if ($char === "\r" || $char === "\n") continue;
            if ($fallback > 0) { $fallback--; continue; }
            if (!$state['skip']) $out .= mb_convert_encoding($char, 'UTF-8', 'Windows-1252');
            continue;
        }
        if ($i >= $length) throw new InvalidArgumentException('The RTF ended unexpectedly.');
        $next = $source[$i];
        if (in_array($next, ['\\', '{', '}'], true)) {
            $i++;
            if ($fallback > 0) $fallback--;
            elseif (!$state['skip']) $out .= $next;
            continue;
        }
        if ($next === '*') { $state['skip'] = true; $i++; continue; }
        if ($next === "'") {
            $hex = substr($source, $i + 1, 2);
            if (!ctype_xdigit($hex) || strlen($hex) !== 2) throw new InvalidArgumentException('The RTF has an invalid text escape.');
            $i += 3;
            if ($fallback > 0) $fallback--;
            elseif (!$state['skip']) $out .= mb_convert_encoding(chr(hexdec($hex)), 'UTF-8', 'Windows-1252');
            continue;
        }
        if (!preg_match('/\G([a-z]+)(-?\d+)? ?/Ai', $source, $match, 0, $i)) {
            $i++;
            if ($fallback > 0) $fallback--;
            elseif (!$state['skip'] && $next === '~') $out .= ' ';
            elseif (!$state['skip'] && $next === '_') $out .= '-';
            continue;
        }
        $i += strlen($match[0]);
        $word = strtolower($match[1]);
        $value = isset($match[2]) ? (int) $match[2] : 0;
        if ($word === 'bin') throw new InvalidArgumentException('RTF binary objects are unsupported. Export to TXT or DOCX.');
        if (in_array($word, ['fonttbl', 'colortbl', 'stylesheet', 'info', 'pict', 'object', 'header', 'footer',
            'headerl', 'headerr', 'footerl', 'footerr', 'footnote', 'annotation', 'fldinst', 'listtable', 'listoverridetable'], true)) {
            $state['skip'] = true;
        }
        if ($word === 'uc') $state['uc'] = max(0, min(10, $value));
        if ($state['skip']) continue;
        if ($word === 'u') {
            $code = $value < 0 ? $value + 65536 : $value;
            if ($code < 0 || $code > 65535 || ($code >= 0xD800 && $code <= 0xDFFF)) {
                throw new InvalidArgumentException('This RTF contains unsupported Unicode characters. Export it to UTF-8 TXT or DOCX to preserve them.');
            }
            $out .= mb_chr($code, 'UTF-8');
            $fallback = $state['uc'];
        } elseif (in_array($word, ['par', 'line', 'row'], true)) $out .= "\n";
        elseif (in_array($word, ['tab', 'cell'], true)) $out .= "\t";
        elseif (isset(['emdash' => 1, 'endash' => 1, 'lquote' => 1, 'rquote' => 1, 'ldblquote' => 1, 'rdblquote' => 1, 'bullet' => 1][$word])) {
            $out .= ['emdash' => '—', 'endash' => '–', 'lquote' => '‘', 'rquote' => '’', 'ldblquote' => '“', 'rdblquote' => '”', 'bullet' => '•'][$word];
        }
    }
    if ($stack) throw new InvalidArgumentException('The RTF is incomplete.');
    return $out;
}

function kns_import_question(string $text): array
{
    return ['text' => $text, 'type' => 'short_answer', 'choices' => [], 'answer' => '',
        'points' => 1, 'warnings' => [], '_labels' => [], '_keys' => []];
}

function kns_import_finish(array $question): array
{
    $question['type'] = $question['choices'] ? 'multiple_choice' : 'short_answer';
    $keys = array_values(array_unique($question['_keys']));
    if (count($keys) > 1) {
        $question['warnings'][] = 'Conflicting explicit answer keys; no answer was selected.';
    } elseif (count($keys) === 1) {
        $key = $keys[0];
        if ($question['type'] === 'short_answer') {
            $question['answer'] = $key;
        } else {
            $letter = strtoupper(rtrim(trim($key), ').'));
            $matches = array_keys($question['_labels'], $letter, true);
            if (!$matches) $matches = array_keys($question['choices'], $key, true);
            if (count($matches) === 1) $question['answer'] = (string) $matches[0];
            else $question['warnings'][] = 'The explicit answer "' . $key . '" does not identify exactly one choice; no answer was selected.';
        }
    }
    if ($question['answer'] === '') $question['warnings'][] = 'No reliable explicit answer key. Set it manually or leave this question for manual grading.';
    if ($question['type'] === 'multiple_choice' && count($question['choices']) < 2) $question['warnings'][] = 'Fewer than two choices were recognized.';
    if ($question['choices']) {
        $expected = array_map(static fn($i) => chr(65 + $i), range(0, count($question['choices']) - 1));
        if ($question['_labels'] !== $expected) $question['warnings'][] = 'Choice labels are missing, repeated, or out of sequence. Check all choices.';
    }
    $question['warnings'] = array_values(array_unique($question['warnings']));
    unset($question['_keys'], $question['_labels']);
    return $question;
}

function kns_import_questions(string $text): array
{
    $questions = [];
    $numbers = [];
    $notices = [];
    $current = null;
    $keySection = false;
    $answerMetadata = false;
    $unrecognized = 0;
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(?:answer\s*key|correct\s*answers|answers|solutions)\s*:?\s*$/i', $line)) {
            $keySection = true;
            $current = null;
            continue;
        }
        if ($keySection) {
            if (preg_match('/^(?:Question\s+)?(\d+)\s*[.):\-]\s*(?:Answer\s*:\s*)?(.+)$/i', $line, $match)
                && isset($numbers[$match[1]]) && count($numbers[$match[1]]) === 1) {
                $questions[$numbers[$match[1]][0]]['_keys'][] = trim($match[2]);
            } else $unrecognized++;
            continue;
        }
        if (preg_match('/^(?:points?|marks?)\s*:\s*(\d+(?:\.\d{1,2})?)\s*$/i', $line, $match) && $current !== null) {
            if ((float) $match[1] >= 0.01 && (float) $match[1] <= 1000) $questions[$current]['points'] = (float) $match[1];
            else $questions[$current]['warnings'][] = 'Source points are out of range; defaulted to 1 point.';
            continue;
        }
        if (preg_match('/^(?:correct\s+answer|answer)\s*:\s*(.+)$/i', $line, $match)) {
            if ($current !== null) $questions[$current]['_keys'][] = trim($match[1]);
            else $unrecognized++;
            $answerMetadata = true;
            continue;
        }
        if (preg_match('/^(?:answers?|correct\s+answers?|answer\s*key|solutions?|explanations?)\s*[:=]/i', $line)) {
            $unrecognized++;
            if ($current !== null) $questions[$current]['warnings'][] = 'Unsupported answer/solution metadata was kept out of student-facing fields. Review it in the extracted text.';
            $answerMetadata = true;
            continue;
        }
        if (preg_match('/^(?:Question\s+(\d+)\s*[:.)\-]|\s*(\d+)\s*[.)])\s*(.*)$/i', $line, $match)) {
            if (count($questions) >= KNS_CLASSWORK_MAX_QUESTIONS) {
                throw new InvalidArgumentException('More than 200 questions were found. Split the source into smaller files.');
            }
            $number = $match[1] !== '' ? $match[1] : $match[2];
            $questions[] = kns_import_question($match[3]);
            $answerMetadata = false;
            $current = count($questions) - 1;
            $numbers[$number][] = $current;
            if (count($numbers[$number]) > 1) $questions[$current]['warnings'][] = 'Repeated question number; separate answer-key matching is ambiguous.';
            continue;
        }
        if (preg_match('/^([A-Z])\s*[.)]\s*(.*)$/i', $line, $match)) {
            if ($current === null) { $unrecognized++; continue; }
            if (count($questions[$current]['choices']) >= 26) throw new InvalidArgumentException('A question has more than 26 choices. Split or edit it manually.');
            $questions[$current]['_labels'][] = strtoupper($match[1]);
            $questions[$current]['choices'][] = $match[2];
            $answerMetadata = false;
            continue;
        }
        if ($answerMetadata) {
            $unrecognized++;
            continue;
        }
        if ($current === null || (str_ends_with($line, '?') && $questions[$current]['text'] !== '' && !$questions[$current]['choices'])) {
            if (str_ends_with($line, '?')) {
                if (count($questions) >= KNS_CLASSWORK_MAX_QUESTIONS) throw new InvalidArgumentException('More than 200 questions were found.');
                $questions[] = kns_import_question($line);
                $current = count($questions) - 1;
                $questions[$current]['warnings'][] = 'Unnumbered question boundary inferred from a question mark; check it.';
            } else $unrecognized++;
            continue;
        }
        $lastChoice = count($questions[$current]['choices']) - 1;
        if ($lastChoice >= 0) $questions[$current]['choices'][$lastChoice] .= "\n" . $line;
        else $questions[$current]['text'] .= "\n" . $line;
        $questions[$current]['warnings'][] = 'Unlabeled continuation text was included. Check question and choice boundaries.';
    }
    if ($unrecognized) $notices[] = $unrecognized . ' line(s) could not be assigned reliably. They remain in the extracted-text preview; add any missing questions manually.';
    foreach ($questions as &$question) $question = kns_import_finish($question);
    unset($question);
    return ['questions' => $questions, 'notices' => $notices];
}

function kns_import_csv(string $text): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $text);
    rewind($stream);
    $firstLine = strtok($text, "\n");
    $delimiter = ',';
    foreach ([";", "\t"] as $candidate) {
        if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) $delimiter = $candidate;
    }
    try {
        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!$header) throw new InvalidArgumentException('The CSV header is missing.');
        $header = array_map(static fn($value) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))), '_'), $header);
        $questionColumn = array_search('question', $header, true);
        if ($questionColumn === false) $questionColumn = array_search('question_text', $header, true);
        if ($questionColumn === false) $questionColumn = array_search('prompt', $header, true);
        if ($questionColumn === false || count(array_unique($header)) !== count($header)) {
            throw new InvalidArgumentException('CSV needs unique headers: question, A, B (through Z as needed), answer, points, type. Only question is required. Use one question per row.');
        }
        $choiceColumns = [];
        $recognized = ['question', 'question_text', 'prompt', 'answer', 'correct_answer', 'points', 'type'];
        foreach ($header as $index => $name) {
            if (preg_match('/^(?:(?:choice|option)_?)?([a-z])$/', $name, $match)) {
                $choiceColumns[$index] = strtoupper($match[1]);
                $recognized[] = $name;
            }
        }
        $notices = [];
        if (array_diff($header, $recognized)) $notices[] = 'Some CSV columns are unsupported. Their content remains in the extracted-text preview.';
        $questions = [];
        $rowNumber = 1;
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            if (count($questions) >= KNS_CLASSWORK_MAX_QUESTIONS) throw new InvalidArgumentException('More than 200 CSV questions were found.');
            if (trim($row[$questionColumn] ?? '') === '') {
                $notices[] = 'CSV row ' . $rowNumber . ' has no question; review it in the extracted text.';
                continue;
            }
            $question = kns_import_question(trim($row[$questionColumn]));
            if (count($row) !== count($header)) $question['warnings'][] = 'CSV field count differs from the header; check this row in the extracted text.';
            foreach ($choiceColumns as $index => $label) {
                if (trim($row[$index] ?? '') !== '') {
                    $question['choices'][] = trim($row[$index]);
                    $question['_labels'][] = $label;
                }
            }
            foreach (['answer', 'correct_answer'] as $name) {
                $index = array_search($name, $header, true);
                if ($index !== false && trim($row[$index] ?? '') !== '') $question['_keys'][] = trim($row[$index]);
            }
            $pointsColumn = array_search('points', $header, true);
            $points = $pointsColumn !== false ? trim($row[$pointsColumn] ?? '') : '';
            if ($points !== '') {
                if (is_numeric($points) && (float) $points >= 0.01 && (float) $points <= 1000 && round((float) $points, 2) == (float) $points) $question['points'] = (float) $points;
                else $question['warnings'][] = 'Invalid source points; defaulted to 1 point.';
            }
            $question = kns_import_finish($question);
            $typeColumn = array_search('type', $header, true);
            $type = $typeColumn !== false ? strtolower(trim($row[$typeColumn] ?? '')) : '';
            if ($type !== '' && $type !== $question['type']) $question['warnings'][] = 'The CSV type differs from the recognized structure. Review and select the correct type.';
            $questions[] = $question;
        }
        return ['questions' => $questions, 'notices' => $notices];
    } finally {
        fclose($stream);
    }
}

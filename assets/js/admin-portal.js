/* Database-backed faculty administration. All writes require an authenticated admin and CSRF. */
(() => {
    "use strict";
    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const icon = name => '<svg class="icon" aria-hidden="true"><use href="#i-'+name+'"/></svg>';
    let data, professors=[], subjects=[], conversations=[], selected=null, editingProfessor=null, editingSubject=null, selectedFile=null;
    let toastTimer, dialogTrigger;
    const drafts=new Map();
    const name=p=>[p.first,p.last].filter(Boolean).join(" ");
    const person=id=>professors.find(p=>String(p.id)===String(id));
    const avatar=p=>'<span class="avatar avatar-green">'+esc(((p.first?.[0]||"P")+(p.last?.[0]||"")).toLocaleUpperCase())+'</span>';
    const badge=(label,pending=false)=>'<span class="status '+(pending?"pending":"active")+'">'+esc(label)+'</span>';
    function toast(message){clearTimeout(toastTimer);$("demo-toast").textContent=message;toastTimer=setTimeout(()=>$("demo-toast").textContent="",7000);}
    async function api(action,values=null){
        const url="admin_action.php?action="+encodeURIComponent(action);
        const options={credentials:"same-origin",headers:{"Accept":"application/json"}};
        if(values!==null){options.method="POST";const body=values instanceof FormData?values:new FormData();if(!(values instanceof FormData))Object.entries(values).forEach(([k,v])=>body.set(k,v??""));body.set("csrf_token",csrf);options.body=body;}
        const response=await fetch(url,options);let result;
        try{result=await response.json();}catch{throw Error("The server response could not be read. Please reload.");}
        if(!response.ok||!result.ok)throw Error(result.message||"Request failed.");
        return result.data;
    }
    async function save(form,action,extra={}){
        if(!form.reportValidity())return;
        const button=form.querySelector('[type="submit"]');if(button?.disabled)return;
        if(button)button.disabled=true;
        try{const values=new FormData(form);Object.entries(extra).forEach(([k,v])=>values.set(k,v??""));const result=await api(action,values);await refresh();form.closest("dialog")?.close();toast(result.message||"Saved.");return result;}
        catch(error){toast(error.message);}finally{if(button)button.disabled=false;}
    }
    function fillOptions(select,rows,first,label,value,keep=true){
        const old=keep?select.value:"";select.replaceChildren(new Option(first,""));
        rows.forEach(row=>select.add(new Option(label(row),value(row))));
        if([...select.options].some(option=>option.value===old))select.value=old;
    }
    async function refresh(){
        data=await api("bootstrap");professors=data.professors;subjects=data.subjects;conversations=data.conversations.sort((a,b)=>b.time.localeCompare(a.time));
        selected=conversations.find(c=>c.professor===selected?.professor)||conversations[0]||null;
        document.querySelectorAll("[data-department-options]").forEach(select=>fillOptions(select,data.departments,select.closest(".filter-bar")?"All departments":"Select department",d=>d.name,d=>d.id));
        fillOptions($("subject-professor"),professors.filter(p=>p.status==="Active"),"Unassigned",p=>"Prof. "+name(p),p=>p.id);
        fillOptions($("enroll-course"),data.courses,"Keep current course",c=>c.code+" · "+c.name,c=>c.id);
        fillOptions($("enroll-student"),data.students,"Select student",s=>[s.first_name,s.last_name].filter(Boolean).join(" ")||s.username,s=>s.id);
        fillOptions($("enroll-section"),subjects.filter(s=>s.sectionId),"Select subject / section",s=>s.code+" · "+s.sections[0]+" · "+s.year+" · "+s.semester,s=>s.sectionId);
        const years=[...new Set([...data.years,String(new Date().getMonth()<7?new Date().getFullYear()-1:new Date().getFullYear())+"-"+String(new Date().getMonth()<7?new Date().getFullYear():new Date().getFullYear()+1)])];
        fillOptions($("report-year"),years,"All academic years",y=>y,y=>y);
        fillOptions($("report-semester"),[...new Set(["First Semester","Second Semester","Summer",...subjects.map(s=>s.semester).filter(Boolean)])],"All semesters",s=>s,s=>s);
        renderProfessors();renderSubjects();renderConversations();renderThread();renderRecent();renderSummary();renderSettings();
        $("admin-load-error").hidden=true;
        await report();
        markVisible();
    }
    function renderSummary(){
        const summary=data.summary;
        const overview=$("screen-overview").querySelectorAll(".metric > strong");
        const unread=conversations.reduce((total,c)=>total+c.unread,0);
        overview[0].textContent=summary.professors;overview[1].textContent=unread;
        overview[2].textContent=summary.reply_rate===null?"—":summary.reply_rate+"%";
        overview[3].textContent=selected?"Prof. "+name(person(selected.professor)):"No conversations";
        const cards=$("screen-professors").querySelectorAll(".metric > strong");
        [summary.professors,summary.active,summary.pending].forEach((v,i)=>cards[i].textContent=v);
        $("subject-assigned-count").textContent=summary.covered;
        $("subject-section-count").textContent=summary.sections;
        $("subject-unassigned-count").textContent=summary.unassigned;
        document.querySelector(".nav-count").textContent=unread;
        document.querySelector(".nav-count").setAttribute("aria-label",unread+" unread professor messages");
        const term=$("screen-overview").querySelectorAll(".term-card strong");
        term[0].textContent=summary.subjects;term[1].textContent=summary.sections;
        document.querySelector(".sidebar-account strong").textContent=data.profile.name;
        const captions=$("screen-overview").querySelectorAll(".metric > p");
        captions[0].textContent=summary.active+" active · "+summary.pending+" pending";
    }
    function renderProfessors(){
        const query=$("professor-search").value.trim().toLocaleLowerCase(),dept=$("professor-department").value,status=$("professor-status").value;
        const rows=professors.filter(p=>(!dept||String(p.departmentId)===dept)&&(!status||p.status===status)&&[name(p),p.facultyId,p.email,p.subjects].join(" ").toLocaleLowerCase().includes(query));
        $("professor-rows").innerHTML=rows.map(p=>'<tr><td><div class="person">'+avatar(p)+'<div><strong>'+esc(name(p))+'</strong><small>'+esc(p.email||"Email not set")+'</small></div></div></td><td>'+esc(p.facultyId||"Not set")+'</td><td>'+esc(p.department)+'</td><td>'+esc(p.subjects)+'</td><td>'+badge(p.status,p.status!=="Active")+'</td><td>'+esc(p.activity)+'</td><td><div class="row-actions"><button class="button small" type="button" data-message="'+p.id+'" aria-label="Message '+esc(name(p))+'">Message</button><button class="button small" type="button" data-edit="'+p.id+'" aria-label="Edit '+esc(name(p))+'">Edit</button></div></td></tr>').join("");
        $("professor-empty").hidden=rows.length>0;
        $("professor-count").textContent="Showing "+rows.length+" of "+professors.length+" professor accounts";
    }
    function renderSubjects(){
        const query=$("subject-search").value.trim().toLocaleLowerCase(),dept=$("subject-department").value,status=$("subject-status").value;
        const rows=subjects.filter(s=>(!dept||String(s.departmentId)===dept)&&(!status||status===(s.professor?"Assigned":"Needs professor"))&&[s.code,s.title,person(s.professor)?name(person(s.professor)):"",...s.sections].join(" ").toLocaleLowerCase().includes(query));
        $("subject-rows").innerHTML=rows.map(s=>'<tr><td><div class="person"><span class="avatar avatar-blue">'+esc(s.code.slice(0,3))+'</span><div><strong>'+esc(s.code+" · "+s.title)+'</strong><small>'+esc(s.year+" · "+s.semester)+(s.capacity?" · Capacity "+s.capacity:"")+'</small></div></div></td><td>'+esc(s.department)+'</td><td>'+esc(person(s.professor)?name(person(s.professor)):"Unassigned")+'</td><td>'+esc(s.sections.join(", ")||"No sections")+'</td><td>'+s.students+'</td><td>'+badge(s.professor?"Assigned":"Needs professor",!s.professor)+'</td><td><button type="button" class="button small" data-subject="'+s.id+'">Manage</button></td></tr>').join("");
        $("subject-empty").hidden=rows.length>0;$("subject-count").textContent=rows.length+" of "+subjects.length+" subject / section records";
    }
    function renderConversations(){
        const query=$("conversation-search").value.trim().toLocaleLowerCase();
        const rows=conversations.filter(c=>[name(person(c.professor)),person(c.professor).department].join(" ").toLocaleLowerCase().includes(query));
        $("conversation-items").innerHTML=rows.map(c=>{const p=person(c.professor);return '<button class="conversation-item" type="button" data-conversation="'+p.id+'" aria-pressed="'+(c===selected)+'">'+avatar(p)+'<span class="conversation-copy"><strong>Prof. '+esc(name(p))+'</strong><span class="conversation-snippet">'+esc(c.preview)+'</span><small>'+esc(p.department)+'</small></span><span class="conversation-time">'+(c.unread?c.unread+" unread":esc(c.time.slice(0,10)))+'</span></button>';}).join("");
        $("conversation-empty").hidden=rows.length>0;
    }
    let marking=false;
    async function markVisible(){
        if(marking||location.hash!=="#messages"||!selected?.unread)return;
        marking=true;
        try{await api("message_read",{professor_id:selected.professor});await refresh();}catch(error){toast(error.message);}finally{marking=false;}
    }
    function scrollThread(){$("chat-thread").scrollTop=$("chat-thread").scrollHeight;}
    function renderThread(){
        $("chat-thread").replaceChildren();
        const p=selected&&person(selected.professor);
        $("chat-name").textContent=p?"Prof. "+name(p):"Choose a professor";
        $("chat-department").textContent=p?p.department+" · Professor account":"Admin ↔ Professor only";
        $("chat-avatar").textContent=p?(p.first[0]||"P")+(p.last?.[0]||""):"P";
        $("message-form").querySelector('[type="submit"]').disabled=!p||p.status!=="Active"||!Number(p.permissions.admin_messaging);
        if(!selected?.messages.length){const empty=document.createElement("p");empty.className="empty-state";empty.textContent=p?"No messages yet. Start a conversation with this professor.":"Register a professor account to start a conversation.";$("chat-thread").append(empty);}
        for(const message of selected?.messages||[]){
            const article=document.createElement("article");article.className="message"+(message.role==="Admin"?" admin-message":"");
            const bubble=document.createElement("div");bubble.className="message-bubble";bubble.textContent=message.text;
            message.attachments.forEach(file=>{const link=document.createElement("a");link.className="message-file";link.href="faculty_attachment.php?id="+file.id;link.textContent=file.original_name+" · Download";bubble.append(link);});
            const meta=document.createElement("p");meta.className="message-meta";meta.textContent=message.time+" · "+message.role+(message.role==="Admin"?(message.read?" · Read":" · Sent"):"");
            article.append(bubble,meta);$("chat-thread").append(article);
        }
        requestAnimationFrame(scrollThread);
    }
    async function selectConversation(id){
        if(selected)drafts.set(selected.professor,$("message-text").value);
        selected=conversations.find(c=>c.professor===String(id))||null;
        $("message-text").value=drafts.get(selected?.professor)||"";selectedFile=null;$("message-attachment").value="";attachment();
        renderConversations();renderThread();
        if(selected){try{await api("message_read",{professor_id:selected.professor});await refresh();}catch(error){toast(error.message);}}
    }
    function attachment(){$("attachment-name").textContent=selectedFile?.name||"";$("attachment-preview").hidden=!selectedFile;}
    function renderRecent(){
        const query=$("recent-search").value.trim().toLocaleLowerCase();
        const rows=conversations.filter(c=>c.messages.length&&[name(person(c.professor)),c.preview].join(" ").toLocaleLowerCase().includes(query)).sort((a,b)=>b.time.localeCompare(a.time)).slice(0,3);
        $("recent-conversations").innerHTML=rows.map(c=>{const p=person(c.professor);return '<div class="recent-row">'+avatar(p)+'<div><strong>Prof. '+esc(name(p))+'</strong><small>Professor account</small></div><div class="recent-topic"><p>'+esc(c.preview)+'</p><small>'+esc(c.time)+'</small></div><button class="button small" type="button" data-open="'+p.id+'">Open →</button></div>';}).join("");
        $("recent-empty").hidden=rows.length>0;
    }
    function renderSettings(){
        const profile=$("profile-form");
        [["first_name",data.profile.first],["last_name",data.profile.last],["email",data.profile.email],["contact",data.profile.contact]].forEach(([field,value])=>{profile.elements.namedItem(field).value=value;});
        ["messages","invitations","assignments"].forEach(key=>$("notification-form").elements.namedItem(key).checked=!!Number(data.preferences[key]));
        $("security-password").textContent=data.profile.hashed?"Hashed password configured":"Legacy password · update recommended";
        $("security-review").textContent=data.profile.passwordChanged||"No password change recorded";
        $("mail-status").textContent=data.mailPending+" queued email(s). Delivery runs through the configured school mail worker.";
        $("request-rows").innerHTML=data.requests.map(r=>'<tr><td>'+esc(r.category)+'</td><td>'+esc(r.full_name)+'<br>'+esc(r.email)+'</td><td class="request-message">'+esc(r.message)+'</td><td>'+esc(r.status)+'</td><td>'+esc(r.created_at)+'</td><td>'+(r.status==="new"?'<button type="button" class="button small" data-review="'+r.id+'">Mark reviewed</button>':"Reviewed")+'</td></tr>').join("");
        $("request-empty").hidden=data.requests.length>0;
    }
    let reportRequest=0;
    async function report(){
        const request=++reportRequest;
        if(Boolean($("report-from").value)!==Boolean($("report-to").value)){ $("report-context").textContent="Choose both message dates to apply the date range.";return; }
        const params=new URLSearchParams({action:"report",year:$("report-year").value,semester:$("report-semester").value,department:$("report-department").value,from:$("report-from").value,to:$("report-to").value});
        try{
            const response=await fetch("admin_action.php?"+params,{credentials:"same-origin"});const result=await response.json();if(!result.ok)throw Error(result.message);const report=result.data;if(request!==reportRequest)return;
            $("screen-reports").querySelector(".metric > p").textContent=report.active+" active · "+report.pending+" pending";
            const cards=$("screen-reports").querySelectorAll(".metric > strong");
            cards[0].textContent=report.professors;cards[1].textContent=report.covered+" / "+report.subjects;cards[2].textContent=report.reply_rate===null?"—":report.reply_rate+"%";
            $("report-context").textContent=($("report-year").value||"All academic years")+" · "+($("report-semester").value||"All semesters")+" · "+($("report-department").selectedOptions[0]?.text||"All departments")+" · "+report.message_scope;
            const numbers=$("screen-reports").querySelectorAll(".quick-summary dd");
            [report.active,report.pending,report.covered+" / "+report.subjects,report.sections,report.conversations,report.unread].forEach((value,i)=>numbers[i].textContent=value);
            const max=Math.max(1,...report.activity.flatMap(m=>[m.subjects,m.sections]));
            document.querySelector(".chart-plot").innerHTML=report.activity.map(m=>'<div class="bar-group"><div><i style="--bar-height:'+m.subjects/max*100+'%"></i><b style="--bar-height:'+m.sections/max*100+'%"></b></div><span>'+esc(m.label)+'</span></div>').join("");
            document.querySelectorAll(".chart-axis span").forEach((span,i)=>span.textContent=Math.round(max*(3-i)/3));
            document.querySelector(".activity-chart").setAttribute("aria-label",report.activity.map(m=>m.label+": "+m.subjects+" subjects and "+m.sections+" sections").join(". "));
            document.querySelector(".chart-data tbody").innerHTML=report.activity.map(m=>'<tr><th scope="row">'+esc(m.label)+'</th><td>'+m.subjects+'</td><td>'+m.sections+'</td></tr>').join("");
        }catch(error){toast(error.message);}
    }
    function openDialog(dialog){dialogTrigger=document.activeElement;dialog.showModal();(dialog.querySelector("[autofocus]")||dialog.querySelector("input,button")).focus();}
    document.querySelectorAll("dialog").forEach(dialog=>{
        dialog.querySelectorAll("[data-close-dialog]").forEach(button=>button.addEventListener("click",()=>dialog.close()));
        dialog.addEventListener("close",()=>dialogTrigger?.isConnected&&dialogTrigger.focus());
        dialog.addEventListener("click",event=>{const r=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom))dialog.close();});
    });
    function professorForm(p=null){
        if(!data.departments.length){toast("Create a department in Settings before registering a professor.");location.hash="settings";return;}
        editingProfessor=p;$("professor-form").reset();
        $("professor-dialog-title").textContent=p?"Edit Professor":"Register Professor";
        $("professor-save").textContent="Save professor";
        $("professor-status-field").hidden=!p;
        $("professor-temporary-field").hidden=!!p;
        if(p){
            $("professor-first").value=p.first;$("professor-last").value=p.last;$("professor-id").value=p.facultyId;$("professor-email").value=p.email;$("professor-contact").value=p.contact;
            $("professor-form-department").value=p.departmentId||"";$("professor-form-status").value=({"Active":"active","Invitation pending":"pending","Suspended":"suspended"})[p.status];
            [["permission-classes","view_classes"],["permission-classwork","manage_classwork"],["permission-grades","record_grades"],["permission-messages","admin_messaging"]].forEach(([id,key])=>$(id).checked=!!Number(p.permissions[key]));
            $("professor-activation").checked=false;
        }
        openDialog($("professor-dialog"));
    }
    function subjectForm(subject=null){
        if(!data.departments.length){toast("Create a department in Settings first.");location.hash="settings";return;}
        editingSubject=subject;$("subject-form").reset();
        $("subject-dialog-title").textContent=subject?"Manage Subject / Section":"Add Subject / Section";$("subject-save").textContent="Save subject / section";
        if(subject){
            $("subject-code").value=subject.code.startsWith("ID ")?"":subject.code;$("subject-name").value=subject.title;
            $("subject-form-department").value=subject.departmentId||"";$("subject-professor").value=subject.professor;
            $("subject-section").value=subject.sections[0]||"";$("subject-capacity").value=subject.capacity||40;
        }
        const year=subject?.year||data.years[0]||new Date().getFullYear()+"-"+(new Date().getFullYear()+1);
        $("subject-year").value=year;$("subject-semester").value=subject?.semester||"First Semester";
        openDialog($("subject-dialog"));
    }
    document.querySelectorAll("[data-register]").forEach(button=>button.addEventListener("click",()=>professorForm()));
    $("professor-rows").addEventListener("click",event=>{const edit=event.target.closest("[data-edit]"),message=event.target.closest("[data-message]");if(edit)professorForm(person(edit.dataset.edit));if(message){location.hash="messages";selectConversation(message.dataset.message);}});
    $("professor-form").addEventListener("submit",async event=>{
        event.preventDefault();const result=await save($("professor-form"),"professor_save",{id:editingProfessor?.id||"",department_id:$("professor-form-department").value,status:$("professor-form-status").value});
        if(result?.activationLink){$("activation-link").value=result.activationLink;openDialog($("activation-dialog"));}
    });
    $("add-subject").addEventListener("click",()=>subjectForm());
    $("subject-rows").addEventListener("click",event=>{const button=event.target.closest("[data-subject]");if(button)subjectForm(subjects.find(s=>s.id===button.dataset.subject));});
    $("subject-form").addEventListener("submit",event=>{event.preventDefault();save($("subject-form"),"subject_save",{subject_id:editingSubject?.subjectId||"",section_id:editingSubject?.sectionId||"",department_id:$("subject-form-department").value});});
    $("enrollment-form").addEventListener("submit",event=>{event.preventDefault();save($("enrollment-form"),"enroll");});
    $("profile-form").addEventListener("submit",event=>{event.preventDefault();save($("profile-form"),"profile");});
    $("notification-form").addEventListener("submit",event=>{event.preventDefault();save($("notification-form"),"preferences");});
    $("department-form").addEventListener("submit",async event=>{event.preventDefault();if(await save($("department-form"),"department"))$("department-form").reset();});
    $("course-form").addEventListener("submit",async event=>{event.preventDefault();if(await save($("course-form"),"course"))$("course-form").reset();});
    $("request-rows").addEventListener("click",async event=>{const button=event.target.closest("[data-review]");if(button)try{await api("request_reviewed",{id:button.dataset.review});await refresh();toast("Request reviewed.");}catch(error){toast(error.message);}});
    $("conversation-items").addEventListener("click",event=>{const button=event.target.closest("[data-conversation]");if(button)selectConversation(button.dataset.conversation);});
    $("recent-conversations").addEventListener("click",event=>{const button=event.target.closest("[data-open]");if(button){location.hash="messages";selectConversation(button.dataset.open);}});
    $("message-attachment").addEventListener("change",()=>{selectedFile=$("message-attachment").files[0]||null;if(selectedFile&&selectedFile.size>5*1024*1024){toast("Maximum attachment size is 5 MiB.");selectedFile=null;}$("message-attachment").value="";attachment();});
    $("remove-attachment").addEventListener("click",()=>{selectedFile=null;attachment();$("message-text").focus();});
    $("message-form").addEventListener("submit",async event=>{
        event.preventDefault();if(!selected)return;const receiverId=selected.professor,sentText=$("message-text").value,sentFile=selectedFile;const button=event.submitter;if(button)button.disabled=true;
        try{const values=new FormData();values.set("professor_id",receiverId);values.set("message",sentText);
            if(selectedFile)values.append(/\.(jpe?g|png|gif|webp)$/i.test(selectedFile.name)?"photos[]":"files[]",selectedFile);
            await api("message_send",values);drafts.delete(receiverId);if(selected?.professor===receiverId&&$("message-text").value===sentText){$("message-text").value="";if(selectedFile===sentFile)selectedFile=null;attachment();}await refresh();toast("Message sent.");$("message-text").focus();
        }catch(error){toast(error.message);}finally{if(button)button.disabled=false;}
    });
    [["professor-search","input",renderProfessors],["professor-department","change",renderProfessors],["professor-status","change",renderProfessors],["subject-search","input",renderSubjects],["subject-department","change",renderSubjects],["subject-status","change",renderSubjects],["conversation-search","input",renderConversations],["recent-search","input",renderRecent]].forEach(([id,event,handler])=>$(id).addEventListener(event,handler));
    [["reset-professor-filters",["professor-search","professor-department","professor-status"],renderProfessors],["reset-subject-filters",["subject-search","subject-department","subject-status"],renderSubjects]].forEach(([id,fields,render])=>$(id).addEventListener("click",()=>{fields.forEach(field=>$(field).value="");render();$(fields[0]).focus();}));
    ["report-year","report-semester","report-department","report-from","report-to"].forEach(id=>$(id).addEventListener("change",report));
    $("export-report").addEventListener("click",()=>{const params=new URLSearchParams({action:"export",year:$("report-year").value,semester:$("report-semester").value,department:$("report-department").value,from:$("report-from").value,to:$("report-to").value});location.href="admin_action.php?"+params;});
    $("open-notifications").addEventListener("click",async()=>{const rows=data.notifications;$("notification-list").replaceChildren();for(const item of rows){const article=document.createElement("article");article.className="panel";const title=document.createElement("h3"),body=document.createElement("p");title.textContent=item.title;body.textContent=item.body+" · "+item.created_at;article.append(title,body);$("notification-list").append(article);}if(!rows.length)$("notification-list").textContent="No notifications yet.";openDialog($("notifications-dialog"));try{await api("notification_read",{});}catch(error){toast(error.message);}});
    $("open-security").addEventListener("click",()=>openDialog($("security-dialog")));
    const labels={overview:"Overview",professors:"Professor accounts",messages:"Admin–professor messages",subjects:"Subjects & sections",reports:"Reports",settings:"Settings"};
    function closeMenu(focus=false){const open=document.querySelector(".admin-sidebar").classList.contains("is-open");document.querySelector(".admin-sidebar").classList.remove("is-open");$("menu-toggle").setAttribute("aria-expanded","false");$("admin-main").inert=false;if(focus&&open)$("menu-toggle").focus();}
    $("menu-toggle").addEventListener("click",()=>{const open=document.querySelector(".admin-sidebar").classList.toggle("is-open");$("menu-toggle").setAttribute("aria-expanded",String(open));$("admin-main").inert=open;});
    matchMedia("(max-width: 800px)").addEventListener("change",()=>closeMenu());
    document.addEventListener("keydown",event=>{if(event.key==="Escape"&&!document.querySelector("dialog[open]"))closeMenu(true);});
    function screen(focus=true){const key=Object.hasOwn(labels,location.hash.slice(1))?location.hash.slice(1):"overview";document.querySelectorAll("[data-screen]").forEach(s=>s.hidden=s.dataset.screen!==key);document.querySelectorAll("[data-screen-link]").forEach(link=>{if(link.dataset.screenLink===key)link.setAttribute("aria-current","page");else link.removeAttribute("aria-current");});$("breadcrumb-label").textContent=labels[key];document.title=labels[key]+" · KNS Portal";closeMenu();if(focus){document.querySelector('[data-screen="'+key+'"] h1').focus({preventScroll:true});window.scrollTo(0,0);}if(key==="messages"){requestAnimationFrame(scrollThread);markVisible();}}
    addEventListener("hashchange",()=>screen());document.querySelectorAll("[data-screen-link]").forEach(link=>link.addEventListener("click",()=>closeMenu()));
    document.querySelector(".admin-skip").addEventListener("click",event=>{event.preventDefault();document.querySelector("[data-screen]:not([hidden]) h1").focus();});
    document.querySelectorAll("form").forEach(form=>form.addEventListener("submit",event=>event.preventDefault()));
    screen(false);
    refresh().catch(error=>{$("admin-load-error").hidden=false;$("admin-load-error").textContent=error.message;toast(error.message);});
})();

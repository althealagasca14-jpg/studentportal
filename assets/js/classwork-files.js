(() => {
"use strict";
document.querySelectorAll("[data-cw-upload]").forEach(fieldset=>{
 let urls=[];
 const preview=fieldset.querySelector(".cw-upload-preview");
 fieldset.addEventListener("change",()=>{
  urls.forEach(URL.revokeObjectURL);urls=[];preview.replaceChildren();
  const files=[...fieldset.querySelectorAll('input[type="file"]')].flatMap(input=>[...input.files]);
  const invalid=files.length>3||files.some(file=>!file.size||file.size>5*1024*1024);
  const submit=fieldset.closest("form").querySelector('[type="submit"]');
  if(submit)submit.disabled=invalid;
  if(invalid){preview.textContent="Choose up to 3 nonempty attachments, maximum 5 MiB each.";return;}
  files.forEach(file=>{
   const card=document.createElement("div"),label=document.createElement("p");
   label.textContent=file.name+" · "+Math.ceil(file.size/1024)+" KiB";card.append(label);
   if(["image/jpeg","image/png","image/gif","image/webp"].includes(file.type)){
    const img=document.createElement("img");const url=URL.createObjectURL(file);urls.push(url);img.src=url;img.alt="Local preview of "+file.name;card.prepend(img);
   }
   preview.append(card);
  });
 });
 addEventListener("pagehide",()=>urls.forEach(URL.revokeObjectURL));
});
})();

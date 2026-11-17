(() => {
"use strict";
document.querySelectorAll("[data-mark-read]").forEach(form=>fetch(form.getAttribute("action"),{method:"POST",body:new FormData(form),credentials:"same-origin"}).catch(()=>{}));
document.querySelectorAll("[data-portal-form]").forEach(form=>form.addEventListener("submit",async event=>{
 event.preventDefault();if(!form.reportValidity())return;
 const button=form.querySelector('[type="submit"]'),status=form.querySelector("[data-form-status]");
 if(button.disabled)return;button.disabled=true;status.textContent="Saving…";
 try{const response=await fetch(form.getAttribute("action"),{method:"POST",body:new FormData(form),credentials:"same-origin",headers:{Accept:"application/json"}});const result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||"Could not save.");
 status.textContent=result.message;form.querySelectorAll('input[type="password"]').forEach(input=>input.value="");
 if(form.dataset.reload==="true")location.reload();
 }catch(error){status.textContent=error.message;}finally{button.disabled=false;}
}));
})();

import {request} from "../core/api.js";
export function createCampaignApi(csrf) {
  const write=(path,method,data={})=>request(path,{method,headers:{"X-CSRF-Token":csrf()},body:JSON.stringify(data)});
  return {
    list:()=>request("/campaigns"), items:()=>request("/engagement-items"),
    load:id=>request(`/campaigns/${id}`),
    save:c=>write(`/campaigns${c.id?`/${c.id}`:""}`,c.id?"PUT":"POST",c),
    remove:id=>write(`/campaigns/${id}`,"DELETE"),
    addItem:item=>write("/engagement-items","POST",item),
    ignore:(id,status)=>write(`/engagement-items/${id}`,"PUT",{status}),
    read:id=>write(`/engagement-items/${id}/read`,"POST"),
    review:(id,retry)=>write(`/campaigns/${id}/review`,"POST",retry?{retry_target_id:retry}:{}),
    publish:(id,token)=>write(`/campaigns/${id}/publish`,"POST",{token,confirmed:true}),
    manual:(id,target)=>write(`/campaigns/${id}/manual-published`,"POST",{target_id:target,confirmed:true})
  };
}

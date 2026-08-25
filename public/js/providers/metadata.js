export const providerMetadata = {
  linkedin: { id:"linkedin", name:"LinkedIn", clientIdLabel:"Client ID", clientSecretLabel:"Client Secret", defaultScopes:["openid","profile","w_member_social"], accountFields:["LinkedIn Account-ID"], capabilities:{ text:true,image:true,video:true,document:true,link:true } },
  instagram: { id:"instagram", name:"Instagram", clientIdLabel:"Instagram App ID", clientSecretLabel:"Instagram App Secret", defaultScopes:["instagram_business_basic","instagram_business_content_publish"], accountFields:["Instagram User ID","Username","Account-Typ"], capabilities:{ text:true,image:true,video:true,document:false,link:false } },
  facebook: { id:"facebook", name:"Facebook", clientIdLabel:"Meta App ID", clientSecretLabel:"Meta App Secret", defaultScopes:["pages_show_list","pages_read_engagement","pages_manage_posts"], accountFields:["Facebook User ID","Page ID","Page Name"], capabilities:{ text:true,image:true,video:true,document:false,link:true } }
};

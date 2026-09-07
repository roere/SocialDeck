"""Only the isolated local API and strict per-app mock credentials are used."""
import json,sys,urllib.request,urllib.error,urllib.parse,http.cookiejar
base,cookie_path,csrf=sys.argv[1:];assert base.startswith('http://127.0.0.1:')
jar=http.cookiejar.MozillaCookieJar(cookie_path);jar.load(ignore_discard=True,ignore_expires=True)
for c in jar:
    if c.expires==0:c.expires=None
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
def req(path,data=None,method='GET',expected=200,secure=True):
    headers={'Accept':'application/json','Content-Type':'application/json'}
    if secure:headers['X-CSRF-Token']=csrf
    r=urllib.request.Request(base+'/api'+path,headers=headers,data=None if data is None else json.dumps(data).encode(),method=method)
    try:response=client.open(r)
    except urllib.error.HTTPError as e:response=e
    raw=response.read();assert response.code==expected,(path.split('?')[0],response.code,expected)
    parsed=json.loads(raw) if raw else {};return parsed.get('data',parsed)
def passed(name):print('PASS Provider Apps '+name)
def config():return next(p for p in req('/admin/providers')['providers'] if p['id']=='linkedin')
def values(letter):return dict(name='Fixture '+letter,clientId='apps-client-'+letter,clientSecret='apps-secret-'+letter,redirectUri=base+'/api/oauth/linkedin/callback',scopes='openid profile w_member_social r_events rw_events w_member_social_feed r_member_social_feed r_member_social',enabled=True,purpose='custom')
root='/admin/providers/linkedin/apps'
req(root,values('a'),'POST',403,False);passed('CSRF bei Anlage')
a=req(root,values('a'),'POST',201)['app'];b=req(root,values('b'),'POST',201)['app'];assert a['id']!=b['id'];assert 'clientSecret' not in a;passed('zwei Apps ohne Secret-Rückgabe')
def path(app,action=''):return root+'/'+str(app['id'])+('/'+action if action else '')
def start(app):
    r=req(path(app,'connect'),{'clientId':'injected','scopes':'injected'},'POST');q=urllib.parse.parse_qs(urllib.parse.urlparse(r['authorizationUrl']).query);assert q['client_id']==[app['clientId']];assert 'injected' not in q['scope'][0];return q['state'][0]
def callback(state,expected=303,**args):return req('/oauth/linkedin/callback?'+urllib.parse.urlencode({'state':state,**args}),expected=expected)
updated=values('b');updated['clientSecret']='';updated['name']='Community Fixture';b=req(path(b),updated,'PUT')['app'];assert b['hasClientSecret'];passed('Bearbeiten mit leerem Secretfeld')
sa=start(a);sb=start(b);callback(sa,code='apps-a-code',provider_app_id=b['id']);callback(sb,code='apps-b-code',provider_app_id=a['id']);passed('paralleler State bindet richtige Credentials trotz Query-Manipulation')
c=config();aa=next(x for x in c['accounts'] if x['providerAppId']==a['id']);ab=next(x for x in c['accounts'] if x['providerAppId']==b['id']);assert aa['externalAccountId']==ab['externalAccountId'] and aa['id']!=ab['id'];assert 'rw_events' in aa['scopes'] and 'rw_events' not in ab['scopes'];assert 'w_member_social_feed' not in aa['scopes'] and 'w_member_social_feed' in ab['scopes'];passed('gleiches Mitglied mit getrennten Konten und Grants')
req(path(a),method='DELETE',expected=409);req(path(b,'disconnect'),{'accountId':aa['id']},'POST',404);req('/admin/providers/facebook/apps/'+str(a['id']),expected=404);passed('Löschschutz und Cross-App-Validierung')
changed=values('a');changed['clientId']='another-client';req(path(a),changed,'PUT',409);passed('Client-ID gebundener Konten geschützt')
callback(start(b),error='invalid_scope');c=config();assert next(x for x in c['accounts'] if x['id']==aa['id'])==aa;ar=next(x for x in c['apps'] if x['id']==a['id'])['oauthResult'];br=next(x for x in c['apps'] if x['id']==b['id'])['oauthResult'];assert ar['result']=='success' and br['result']=='failed' and br['providerAppId']==b['id'];passed('Fehler B verändert weder Konto noch Fehlerstatus A')
# Temporarily disable earlier connections to exercise deterministic capability selection.
originals=[x for x in c['apps'] if x['id'] not in [a['id'],b['id']]]
for original in originals:
    v={k:original[k] for k in ['name','clientId','redirectUri','scopes','enabled','purpose']};v.update(clientSecret='',enabled=False);req(path(original),v,'PUT')
c=config();m=c['capabilityMatrix'];assert all(m[k]['providerAppId']==a['id'] for k in ['posting','eventsRead','eventsWrite']);assert all(m[k]['providerAppId']==b['id'] for k in ['comments','feed','organizationDiscovery','organizationPublishing']);passed('Capability-Matrix über getrennte Grants')
channel=next(x for x in c['channels'] if x['socialAccountId']==aa['id'] and x['channelType']=='personal');req('/admin/linkedin/test-post',dict(baseText='Synthetic',text='Synthetic',linkUrl='',visibility='PUBLIC',confirmed=True),'POST');passed('Posting mit Token A')
item=req('/engagement-items',dict(provider_id='linkedin',social_account_id=aa['id'],external_post_url='https://example.test/apps',external_post_urn='urn:li:share:987'),'POST')['item'];req('/engagement-items/'+str(item['id'])+'/read',{},'POST');passed('bekannter Post mit Token B gelesen')
campaign=req('/campaigns',dict(name='App selection fixture',base_reply_text='Synthetic reply',targets=[dict(engagement_item_id=int(item['id']),enabled=True,reply_is_customized=False)]),'POST')['campaign'];assert int(campaign['targets'][0]['social_account_id'])==ab['id'];review=req('/campaigns/'+str(campaign['id'])+'/review',{},'POST')['review'];result=req('/campaigns/'+str(campaign['id'])+'/publish',dict(confirmed=True,token=review['token']),'POST')['campaign'];assert result['targets'][0]['status']=='published';passed('Kampagne speichert und nutzt Kommentar-App B')
req(path(b,'disconnect'),{'accountId':ab['id']},'POST');c=config();assert next(x for x in c['accounts'] if x['id']==aa['id'])==aa;assert not c['capabilityMatrix']['comments']['available'];passed('Disconnect B erhält A; manuelle Capability bleibt')
req(path(b),method='DELETE',expected=409);passed('historische Kontoreferenzen blockieren Löschen')
unused=req(root,dict(values('b'),name='Unused',clientId='unused',enabled=False),'POST',201)['app'];req(path(unused),method='DELETE');passed('unbenutzte App löschbar')
# A changed redirect invalidates only the in-flight attempt, before token exchange.
state=start(a);v=values('a');v['clientSecret']='';v['redirectUri']=base+'/api/oauth/linkedin/callback?changed=1';req(path(a),v,'PUT');callback(state,expected=403,code='apps-a-code');passed('Credential-Änderung während OAuth sicher abgelehnt')
# Keep fixture accounts for final DB encryption checks, disabled so regression choices remain unchanged.
for app,letter in [(a,'a'),(b,'b')]:
    v=values(letter);v['enabled']=False;v['clientSecret']='';req(path(app),v,'PUT')
for original in originals:
    v={k:original[k] for k in ['name','clientId','redirectUri','scopes','enabled','purpose']};v['clientSecret']='';req(path(original),v,'PUT')

"""Scope snapshots over authenticated HTTP against the isolated LinkedIn mock only."""
import json,sys,urllib.request,urllib.error,urllib.parse,http.cookiejar
base,cookie_path,csrf=sys.argv[1:]
assert base.startswith('http://127.0.0.1:')
jar=http.cookiejar.MozillaCookieJar(cookie_path);jar.load(ignore_discard=True,ignore_expires=True)
for c in jar:
    if c.expires==0:c.expires=None
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
def req(path,data=None,method='GET',expected=200):
    headers={'Accept':'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'}
    r=urllib.request.Request(base+'/api'+path,headers=headers,data=None if data is None else json.dumps(data).encode(),method=method)
    try:response=client.open(r)
    except urllib.error.HTTPError as e:response=e
    raw=response.read();assert response.code==expected,(path.split('?')[0],response.code)
    return json.loads(raw).get('data',{}) if raw else {}
def config():return next(p for p in req('/admin/providers')['providers'] if p['id']=='linkedin')
def save(scopes):req('/admin/providers/linkedin',{'enabled':True,'clientId':original['clientId'],'clientSecret':'','redirectUri':original['redirectUri'],'scopes':' '.join(scopes)},'PUT')
def start(scopes):
    save(scopes);r=req('/oauth/linkedin/start?scope=injected');q=urllib.parse.parse_qs(urllib.parse.urlparse(r['authorizationUrl']).query);assert q['scope'][0].split()==scopes
    return q['state'][0]
def callback(state,**args):req('/oauth/linkedin/callback?'+urllib.parse.urlencode({'state':state,**args}),expected=303)
def passed(name):print('PASS OAuth HTTP '+name)
original=config();before=original['accounts'];a=['openid','profile','w_member_social','w_member_social_feed','r_member_social_feed'];b=['openid','profile','w_member_social']
try:
    state=start(a);save(b);callback(state,error='invalid_scope',error_description='sensitive-description code=private')
    c=config();r=c['oauthResult'];assert r['requestedScopes']==a and r['oauthErrorCategory']=='scope' and r['existingConnection'];assert c['accounts']==before;assert 'sensitive-description' not in json.dumps(c);passed('Request A bleibt nach Konfiguration B erhalten; Accountdaten unverändert')
    for error,category in [('access_denied','access_denied'),('unauthorized_scope_error','scope'),('server_error','authorization')]:
        state=start(a);callback(state,error=error,error_description='scope permission');assert config()['oauthResult']['oauthErrorCategory']==category;passed('Fehlerkategorie '+category)
    for code,category in [('invalid-client','token_exchange'),('identity-failure','userinfo')]:
        state=start(a);req('/oauth/linkedin/callback?'+urllib.parse.urlencode({'state':state,'code':code}),expected=422 if code=='invalid-client' else 502);r=config()['oauthResult'];assert r['requestedScopes']==a and r['oauthErrorCategory']==category;passed(category+' mit Snapshot ohne Scope-Diagnose')
    state=start(a);callback(state,code='basic-code');c=config();assert c['scopes'].split()==a and c['accounts'][0]['scopes'].split()==b;assert c['oauthResult']['grantedScopes']==b;assert not c['accounts'][0]['campaignCapabilities']['write'] and not c['accounts'][0]['campaignCapabilities']['read'];assert c['channelAccess']['status']=='permission_required';passed('5 konfiguriert, 3 gewährt; Kampagne und Organization verwenden Grants')
finally:
    save(original['scopes'].split());state=req('/oauth/linkedin/start');q=urllib.parse.parse_qs(urllib.parse.urlparse(state['authorizationUrl']).query);callback(q['state'][0],code='good-code')

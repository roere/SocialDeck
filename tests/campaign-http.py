"""Authenticated HTTP checks; called only by the isolated run-all mock suite."""
import sys, json, urllib.request, urllib.error, http.cookiejar
base, cookie_path, csrf = sys.argv[1:]
assert base.startswith('http://127.0.0.1:')
cookies = http.cookiejar.MozillaCookieJar(cookie_path)
cookies.load(ignore_discard=True, ignore_expires=True)
for cookie in cookies:
    if cookie.expires == 0: cookie.expires = None  # curl represents session cookies with expiry 0
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
def request(path, method='GET', data=None, expected=200, token=True):
    headers = {'Content-Type': 'application/json'}
    if token: headers['X-CSRF-Token'] = csrf
    req = urllib.request.Request(base + '/api' + path, data=None if data is None else json.dumps(data).encode(), headers=headers, method=method)
    try:
        response = client.open(req)
    except urllib.error.HTTPError as error:
        response = error
    body = json.loads(response.read())
    assert response.code == expected, (path, response.code, body)
    print('PASS Campaign HTTP', method, path, expected)
    return body.get('data', {})
assert request('/engagement-items')['items'] == [], 'empty feed must be usable'
item = request('/engagement-items', 'POST', {'provider_id': 'facebook', 'external_post_url': 'https://example.test/campaign-http', 'post_excerpt': '<script>unsafe</script>'})['item']
request('/engagement-items/' + str(item['id']) + '/read', 'POST', {}, 422)
c = request('/campaigns', 'POST', {'name': 'HTTP Campaign', 'base_reply_text': 'Hallo', 'targets': []})['campaign']
p = '/campaigns/' + str(c['id'])
try:
    request(p + '/review', 'POST', {}, 422)
    c['targets'] = [{'engagement_item_id': int(item['id']), 'enabled': True, 'reply_is_customized': False}]
    c = request(p, 'PUT', c)['campaign']
    assert c['targets'][0]['reply_text'] == 'Hallo'
    request(p, 'GET')
    request(p + '/publish', 'POST', {}, 409)
    review = request(p + '/review', 'POST', {})['review']
    assert review['targets'][0]['reply_text'] == 'Hallo'
    assert review['targets'][0]['publishable'] is False
    request(p + '/publish', 'POST', {'token': review['token'], 'confirmed': True}, 403, token=False)
    result = request(p + '/publish', 'POST', {'token': review['token'], 'confirmed': True})['campaign']
    assert result['targets'][0]['status'] != 'published', 'manual target must not fake publication'
    request(p + '/publish', 'POST', {'token': review['token'], 'confirmed': True}, 409)
    request(p + '/manual-published', 'POST', {'target_id': result['targets'][0]['id'], 'confirmed': True})
finally:
    request(p, 'DELETE', {})
request(p, expected=404)

"""KissCore API Client — zero-dependency Python client for [err, data] protocol."""

from __future__ import annotations

import json
import urllib.request
import urllib.error
import urllib.parse
from typing import Any, TypeVar, Generic, Callable, Optional

T = TypeVar('T')

KissResult = tuple[str | None, Any]


class Client:
    """KissCore API client using stdlib urllib (zero dependencies).

    Usage:
        api = Client('https://api.example.com', headers={'Authorization': 'Bearer token'})

        err, user = api.get('/users/123')
        if err:
            print(f'Error: {err}')
            return

        err, order = api.post('/orders', {'product_id': 42, 'quantity': 1})
    """

    def __init__(
        self,
        base_url: str,
        *,
        headers: dict[str, str] | None = None,
        timeout: int = 30,
        on_request: Callable[[str, str], dict[str, str]] | None = None,
        on_error: Callable[[str, Any, int], None] | None = None,
    ) -> None:
        self._base_url = base_url.rstrip('/')
        self._headers = headers or {}
        self._timeout = timeout
        self._on_request = on_request
        self._on_error = on_error

    def get(self, path: str, query: dict[str, Any] | None = None) -> KissResult:
        return self.request('GET', path, query=query)

    def post(self, path: str, body: Any = None) -> KissResult:
        return self.request('POST', path, body=body)

    def put(self, path: str, body: Any = None) -> KissResult:
        return self.request('PUT', path, body=body)

    def delete(self, path: str, body: Any = None) -> KissResult:
        return self.request('DELETE', path, body=body)

    def request(
        self,
        method: str,
        path: str,
        body: Any = None,
        query: dict[str, Any] | None = None,
    ) -> KissResult:
        url = self._base_url + '/' + path.lstrip('/')
        if query:
            url += '?' + urllib.parse.urlencode(query)

        headers = {'Accept': 'application/json', **self._headers}

        if self._on_request:
            extra = self._on_request(method, path)
            headers.update(extra)

        data = None
        if body is not None and method != 'GET':
            data = json.dumps(body, ensure_ascii=False).encode('utf-8')
            headers['Content-Type'] = 'application/json'

        req = urllib.request.Request(url, data=data, headers=headers, method=method)

        try:
            with urllib.request.urlopen(req, timeout=self._timeout) as resp:
                status = resp.status
                raw = resp.read().decode('utf-8')
        except urllib.error.HTTPError as e:
            status = e.code
            raw = e.read().decode('utf-8')
        except urllib.error.URLError as e:
            return (f'e_network: {e.reason}', None)
        except TimeoutError:
            return ('e_timeout', None)

        if not raw:
            return ('e_empty_response', None)

        try:
            parsed = json.loads(raw)
        except json.JSONDecodeError:
            return ('e_invalid_response', raw)

        if not isinstance(parsed, list) or len(parsed) != 2:
            return ('e_invalid_response', parsed)

        err, result_data = parsed

        if err is not None and self._on_error:
            self._on_error(err, result_data, status)

        return (err, result_data)

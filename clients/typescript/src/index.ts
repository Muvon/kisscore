/**
 * KissCore API Client
 * Zero-dependency TypeScript client for KissCore [err, data] response protocol
 */

export type KissResult<T> = [null, T] | [string, unknown];

export interface ClientOptions {
	/** Default headers sent with every request */
	headers?: Record<string, string>;
	/** Request timeout in milliseconds (default: 30000) */
	timeout?: number;
	/** Called before every request — return headers to merge */
	onRequest?: (method: string, path: string) => Record<string, string> | Promise<Record<string, string>>;
	/** Called on every error response */
	onError?: (err: string, data: unknown, status: number) => void;
}

export interface RequestOptions {
	/** Additional headers for this request */
	headers?: Record<string, string>;
	/** Query string parameters */
	query?: Record<string, string | number | boolean>;
	/** AbortSignal for cancellation */
	signal?: AbortSignal;
}

export interface KissClient {
	get<T = unknown>(path: string, options?: RequestOptions): Promise<KissResult<T>>;
	post<T = unknown>(path: string, body?: unknown, options?: RequestOptions): Promise<KissResult<T>>;
	put<T = unknown>(path: string, body?: unknown, options?: RequestOptions): Promise<KissResult<T>>;
	delete<T = unknown>(path: string, body?: unknown, options?: RequestOptions): Promise<KissResult<T>>;
	request<T = unknown>(method: string, path: string, body?: unknown, options?: RequestOptions): Promise<KissResult<T>>;
}

export function createClient(baseUrl: string, opts: ClientOptions = {}): KissClient {
	const {
		headers: defaultHeaders = {},
		timeout = 30_000,
		onRequest,
		onError,
	} = opts;

	async function request<T>(
		method: string,
		path: string,
		body?: unknown,
		options: RequestOptions = {},
	): Promise<KissResult<T>> {
		const url = buildUrl(baseUrl, path, options.query);

		const headers: Record<string, string> = {
			'Accept': 'application/json',
			...defaultHeaders,
			...options.headers,
		};

		if (onRequest) {
			const extra = await onRequest(method, path);
			Object.assign(headers, extra);
		}

		const init: RequestInit = {
			method,
			headers,
			signal: options.signal ?? AbortSignal.timeout(timeout),
		};

		if (body !== undefined && method !== 'GET') {
			headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(body);
		}

		let response: Response;
		try {
			response = await fetch(url, init);
		} catch (e) {
			const err = e instanceof Error ? e.message : 'e_network';
			return [err, null] as KissResult<T>;
		}

		let parsed: unknown;
		try {
			parsed = await response.json();
		} catch {
			return ['e_invalid_response', null] as KissResult<T>;
		}

		// KissCore protocol: [err, data]
		if (!Array.isArray(parsed) || parsed.length !== 2) {
			return ['e_invalid_response', parsed] as KissResult<T>;
		}

		const [err, data] = parsed as [string | null, unknown];

		if (err) {
			onError?.(err, data, response.status);
			return [err, data] as KissResult<T>;
		}

		return [null, data as T];
	}

	return {
		get: <T>(path: string, options?: RequestOptions) =>
			request<T>('GET', path, undefined, options),
		post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
			request<T>('POST', path, body, options),
		put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
			request<T>('PUT', path, body, options),
		delete: <T>(path: string, body?: unknown, options?: RequestOptions) =>
			request<T>('DELETE', path, body, options),
		request,
	};
}

function buildUrl(
	base: string,
	path: string,
	query?: Record<string, string | number | boolean>,
): string {
	const url = base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
	if (!query || Object.keys(query).length === 0) {
		return url;
	}
	const params = new URLSearchParams();
	for (const [k, v] of Object.entries(query)) {
		params.set(k, String(v));
	}
	return url + '?' + params.toString();
}

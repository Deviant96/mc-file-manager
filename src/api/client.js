// Thin REST client. Reads boot config injected by the PHP admin page and
// attaches the WordPress REST nonce to every request.

const boot = window.MCFM_BOOT || {
  restUrl: '',
  nonce: '',
  settings: {},
  user: {},
  maxUpload: 0,
  version: '0',
};

const base = (boot.restUrl || '').replace(/\/$/, '');

function buildUrl(path, params) {
  const url = new URL(`${base}${path}`, window.location.origin);
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, value);
      }
    });
  }
  return url.toString();
}

async function request(path, { method = 'GET', params, body, isForm = false } = {}) {
  const headers = { 'X-WP-Nonce': boot.nonce };
  let payload;

  if (isForm) {
    payload = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const response = await fetch(buildUrl(path, params), {
    method,
    headers,
    credentials: 'same-origin',
    body: payload,
  });

  const text = await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch (e) {
      data = text;
    }
  }

  if (!response.ok) {
    const message = (data && data.message) || `Request failed (${response.status})`;
    const error = new Error(message);
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

export const api = {
  boot,
  rawUrl(path, params) {
    return buildUrl(path, { ...(params || {}), _wpnonce: boot.nonce });
  },
  getTree() {
    return request('/tree');
  },
  getChildren(path) {
    return request('/children', { params: { path } });
  },
  getBreadcrumbs(path) {
    return request('/breadcrumbs', { params: { path } });
  },
  list(path) {
    return request('/list', { params: { path } });
  },
  createFolder(path, name) {
    return request('/folder', { method: 'POST', body: { path, name } });
  },
  createFile(path, name, content = '') {
    return request('/file', { method: 'POST', body: { path, name, content } });
  },
  readFile(path) {
    return request('/file', { params: { path } });
  },
  rename(path, name) {
    return request('/rename', { method: 'POST', body: { path, name } });
  },
  move(source, destination) {
    return request('/move', { method: 'POST', body: { source, destination } });
  },
  copy(source, destination) {
    return request('/copy', { method: 'POST', body: { source, destination } });
  },
  remove(paths) {
    return request('/delete', { method: 'POST', body: { paths } });
  },
  restore(id) {
    return request('/restore', { method: 'POST', body: { id } });
  },
  save(path, content) {
    return request('/save', { method: 'POST', body: { path, content } });
  },
  listSnapshots(path) {
    return request('/snapshots', { params: { path } });
  },
  readSnapshot(id) {
    return request(`/snapshot/${id}`);
  },
  restoreSnapshot(id) {
    return request('/snapshot/restore', { method: 'POST', body: { id } });
  },
  search(query, path, scope = 'down') {
    return request('/search', { params: { query, path, scope } });
  },
  upload(path, file, onProgress) {
    return new Promise((resolve, reject) => {
      const form = new FormData();
      form.append('file', file);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', buildUrl('/upload', { path }));
      xhr.setRequestHeader('X-WP-Nonce', boot.nonce);
      xhr.withCredentials = true;
      xhr.upload.onprogress = (e) => {
        if (onProgress && e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      };
      xhr.onload = () => {
        let data = null;
        try {
          data = JSON.parse(xhr.responseText);
        } catch (e) {
          data = null;
        }
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(data);
        } else {
          reject(new Error((data && data.message) || 'Upload failed'));
        }
      };
      xhr.onerror = () => reject(new Error('Upload failed'));
      xhr.send(form);
    });
  },
  downloadUrl(path) {
    return this.rawUrl('/download', { path });
  },
  rawFileUrl(path) {
    return this.rawUrl('/raw', { path });
  },
  properties(path) {
    return request('/properties', { params: { path } });
  },
  logs(page = 1, perPage = 50) {
    return request('/logs', { params: { page, per_page: perPage } });
  },
  trash() {
    return request('/trash');
  },
  purge(id) {
    return request('/purge', { method: 'POST', body: { id } });
  },
  getSettings() {
    return request('/settings');
  },
  updateSettings(settings) {
    return request('/settings', { method: 'POST', body: settings });
  },
  getRecent() {
    return request('/recent');
  },
  addRecent(path) {
    return request('/recent', { method: 'POST', body: { path } });
  },
  editorOpen(path) {
    return request('/editor/open', { method: 'POST', body: { path } });
  },
  editorClose(path) {
    return request('/editor/close', { method: 'POST', body: { path } });
  },
  editorHeartbeat(path) {
    return request('/editor/heartbeat', { method: 'POST', body: { path } });
  },
  editorPeers(path) {
    return request('/editor/peers', { params: { path } });
  },
  createArchive(paths, name, destination = '') {
    return request('/archive', { method: 'POST', body: { paths, name, destination } });
  },
  extractArchive(path, destination = '') {
    return request('/extract', { method: 'POST', body: { path, destination } });
  },
  chmod(path, mode) {
    return request('/chmod', { method: 'POST', body: { path, mode } });
  },
};

export function formatBytes(bytes) {
  if (bytes === 0 || bytes == null) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  const value = bytes / Math.pow(1024, i);
  return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

export function formatDate(value) {
  if (!value) return '—';
  const d = typeof value === 'number' ? new Date(value * 1000) : new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString();
}

const ICONS = {
  folder: 'dashicons-portfolio',
  image: 'dashicons-format-image',
  code: 'dashicons-editor-code',
  text: 'dashicons-media-text',
  archive: 'dashicons-media-archive',
  default: 'dashicons-media-default',
};

export function iconFor(entry) {
  if (entry.isDir) return ICONS.folder;
  const ext = (entry.ext || '').toLowerCase();
  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'].includes(ext)) return ICONS.image;
  if (['php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'css', 'scss', 'json', 'html', 'xml', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'sh', 'sql'].includes(ext)) return ICONS.code;
  if (['zip', 'tar', 'gz', 'rar', '7z'].includes(ext)) return ICONS.archive;
  if (['txt', 'md', 'log', 'ini', 'conf', 'yml', 'yaml', 'csv'].includes(ext)) return ICONS.text;
  return ICONS.default;
}

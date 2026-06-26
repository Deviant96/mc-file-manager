import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './styles/main.css';

const mountEl = document.getElementById('mcfm-app');

if (mountEl) {
  const app = createApp(App);
  app.use(createPinia());
  app.mount(mountEl);
}

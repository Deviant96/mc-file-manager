import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './styles/main.css';

const mountEl = document.getElementById('mcfm-app');

// Guard against WordPress loading the module more than once.
if (mountEl && !mountEl.__mcfmMounted) {
  mountEl.__mcfmMounted = true;
  const app = createApp(App);
  app.use(createPinia());
  app.mount(mountEl);
}

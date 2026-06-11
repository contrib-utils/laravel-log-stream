import { createApp } from 'vue';
import App from './App.vue';
import '../css/app.css';

const app = createApp(App, {
    config: window.__LOGSCOPE__ ?? {},
});

app.mount('#logscope-app');

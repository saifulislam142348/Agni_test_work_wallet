import './bootstrap';
import { createApp } from 'vue'
import router from './router'
import App from './pages/App.vue'

// Import Tailwind CSS
import '../css/app.css'

createApp(App).use(router).mount('#app')

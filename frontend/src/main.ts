import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './styles/global.scss'
import { useNavCategories } from './composables/useNavCategories'

useNavCategories().load()

createApp(App).use(router).mount('#app')

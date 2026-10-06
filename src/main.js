import '../css/nextdiary.scss'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import { applyLegacyCssVariables } from './legacyCssVariables.js'

applyLegacyCssVariables()

const app = createApp(App)

// `t` and `n` are the server-provided translation helpers (OC.L10N),
// exposed to all templates exactly like the former Vue 2 global mixin.
app.config.globalProperties.t = t
app.config.globalProperties.n = n

app.use(router)
app.mount('#vue-content')

export default app

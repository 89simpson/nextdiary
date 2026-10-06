import { createApp } from 'vue'
import SettingsPage from './SettingsPage.vue'
import { applyLegacyCssVariables } from './legacyCssVariables.js'

applyLegacyCssVariables()

const app = createApp(SettingsPage)

// Server-provided translation helpers (OC.L10N), see main.js
app.config.globalProperties.t = t
app.config.globalProperties.n = n

app.mount('#nextdiary-settings')

import Vue from 'vue'
import SettingsPage from './SettingsPage.vue'

Vue.mixin({ methods: { t, n } })

new Vue({
	el: '#nextdiary-settings',
	render: h => h(SettingsPage),
})

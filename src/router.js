import Vue from 'vue'
import VueRouter from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import Diary from './Diary.vue'
import DayView from './DayView.vue'
import EntryEditor from './Editor.vue'
import TagEntriesView from './TagEntriesView.vue'
import SymptomEntriesView from './SymptomEntriesView.vue'
import MedicationEntriesView from './MedicationEntriesView.vue'

Vue.use(VueRouter)

export default new VueRouter({
	mode: 'history',
	base: generateUrl('apps/nextdiary'),
	routes: [
		{
			path: '/',
			component: Diary,
			redirect: { name: 'day', params: { date: moment().format('YYYY-MM-DD') } },
			children: [
				{
					path: 'day/:date',
					name: 'day',
					component: DayView,
					props: true,
				},
				{
					path: 'entry/:id',
					name: 'entry',
					component: EntryEditor,
					props: true,
				},
				{
					path: 'tag/:tagId',
					name: 'tag-entries',
					component: TagEntriesView,
					props: true,
				},
				{
					path: 'symptom/:symptomId',
					name: 'symptom-entries',
					component: SymptomEntriesView,
					props: true,
				},
				{
					path: 'medication/:medicationId',
					name: 'medication-entries',
					component: MedicationEntriesView,
					props: true,
				},
			],
		},
		{
			// Legacy redirect
			path: '/date/:date',
			redirect: to => ({ name: 'day', params: { date: to.params.date } }),
		},
	],
})

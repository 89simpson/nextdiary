import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import Diary from './Diary.vue'
import DayView from './DayView.vue'
import EntryEditor from './Editor.vue'
import TagEntriesView from './TagEntriesView.vue'
import SymptomEntriesView from './SymptomEntriesView.vue'
import MedicationEntriesView from './MedicationEntriesView.vue'
import { isValidDate, isValidId } from './apiUrl.js'

// Route parameters are used in API URLs: only accept the expected formats.
// Anything else (e.g. a crafted `..%5C..%5Cremote.php` deep link) is an unknown path.
const ID = '(\\d+)'
const ID_PARAMS = ['id', 'tagId', 'symptomId', 'medicationId']
// Dates are checked by the guard below, not by a route pattern: depending on
// the user's locale, moment writes them with non-Latin digits, and the route
// pattern would only see their percent-encoded form.

const router = createRouter({
	history: createWebHistory(generateUrl('apps/nextdiary')),
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
					path: `entry/:id${ID}`,
					name: 'entry',
					component: EntryEditor,
					props: true,
				},
				{
					path: `tag/:tagId${ID}`,
					name: 'tag-entries',
					component: TagEntriesView,
					props: true,
				},
				{
					path: `symptom/:symptomId${ID}`,
					name: 'symptom-entries',
					component: SymptomEntriesView,
					props: true,
				},
				{
					path: `medication/:medicationId${ID}`,
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
		{
			// Unknown paths and malformed parameters: back to the start view
			path: '/:pathMatch(.*)*',
			redirect: '/',
		},
	],
})

// Malformed parameters lead to the start view. Ids: digits only is not
// enough, they must also be safe integers.
router.beforeEach(to => {
	for (const name of ID_PARAMS) {
		if (name in to.params && !isValidId(to.params[name])) {
			return '/'
		}
	}
	if ('date' in to.params && !isValidDate(to.params.date)) {
		return '/'
	}
})

export default router

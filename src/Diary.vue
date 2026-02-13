<template>
	<NcContent id="nextdiary-content" app-name="nextdiary">
		<NcAppNavigation>
			<div class="navigation-wrapper">
				<NcButton class="icon icon-view-previous"
					:aria-label="t('nextdiary', 'Previous day')"
					@click="goPrevDay" />
				<NcDatetimePicker ref="datepicker"
					v-model="selectedDate"
					class="diary-datetimepicker"
					type="date"
					:open="calendarOpen"
					@change="onDateChange"
					@calendar-change="onCalendarChange"
					@panel-change="onCalendarPanelUpdate" />
				<NcButton class="open-calendar"
					@click="openCalendar">
					{{ formattedDate }}
				</NcButton>
				<NcButton v-if="showNextDayButton"
					class="icon icon-view-next"
					:aria-label="t('nextdiary', 'Next day')"
					@click="goNextDay" />
			</div>
			<template #list>
				<ul>
					<NcListItem v-for="entry in lastEntries"
						:key="entry.id"
						:title="formatEntryTitle(entry)"
						:bold="false"
						:compact="true"
						counter-type="highlighted"
						@click="goToEntry(entry)">
						<template #icon>
							<NcAppNavigationIconBullet v-if="isActiveEntry(entry)" color="0082c9" />
							<NcAppNavigationIconBullet v-else color="FFFFFF" />
						</template>
						<template #subtitle>
							{{ stripMarkdown(entry.excerpt) }}
						</template>
					</NcListItem>
				</ul>
			</template>
			<template #footer>
				<NcAppNavigationItem class="export" :name="t('nextdiary', 'Export')" icon="icon-download">
					<template #actions>
						<NcActionLink :href="pdfDownloadLink">
							<template #icon>
								<FilePdfBox :size="20" />
								{{ t('nextdiary', 'as PDF') }}
							</template>
						</NcActionLink>
						<NcActionLink :href="markdownDownloadLink">
							<template #icon>
								<Markdown :size="20" />
								{{ t('nextdiary', 'as Markdown') }}
							</template>
						</NcActionLink>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<router-view
				@entry-changed="onEntryChanged"
				@navigate-date="onDateChange" />
		</NcAppContent>
		<div id="nextdiary-right-sidebar" :class="{ 'mobile-open': mobileSidebarOpen }">
			<template v-for="sectionKey in sidebarOrder">
				<div v-if="sectionKey === 'tags' && settings.show_tags"
					:key="sectionKey"
					class="sidebar-section"
					:class="{ expanded: expandedSection === 'tags' }">
					<h4 class="sidebar-title" @click="toggleSection('tags')">
						<ChevronRight v-if="expandedSection !== 'tags'" :size="16" />
						<ChevronDown v-else :size="16" />
						{{ t('nextdiary', 'Tags') }}
					</h4>
					<template v-if="expandedSection === 'tags'">
						<input v-if="searchOpen === 'tags'"
							ref="searchTags"
							v-model="tagSearchQuery"
							type="text"
							class="sidebar-search-input"
							:placeholder="t('nextdiary', 'Search...')">
						<TagCloud :tags="filteredTags" :active-tag-id="activeTagId" @select-tag="onMobileTagSelect" />
					</template>
				</div>
				<div v-if="sectionKey === 'symptoms' && settings.show_symptoms"
					:key="sectionKey"
					class="sidebar-section"
					:class="{ expanded: expandedSection === 'symptoms' }">
					<h4 class="sidebar-title" @click="toggleSection('symptoms')">
						<ChevronRight v-if="expandedSection !== 'symptoms'" :size="16" />
						<ChevronDown v-else :size="16" />
						{{ t('nextdiary', 'Symptoms') }}
					</h4>
					<template v-if="expandedSection === 'symptoms'">
						<input v-if="searchOpen === 'symptoms'"
							ref="searchSymptoms"
							v-model="symptomSearchQuery"
							type="text"
							class="sidebar-search-input"
							:placeholder="t('nextdiary', 'Search...')">
						<SymptomCloud :symptoms="filteredSymptoms" :active-symptom-id="activeSymptomId" @select-symptom="onMobileSymptomSelect" />
					</template>
				</div>
				<div v-if="sectionKey === 'medications' && settings.show_medications"
					:key="sectionKey"
					class="sidebar-section"
					:class="{ expanded: expandedSection === 'medications' }">
					<h4 class="sidebar-title" @click="toggleSection('medications')">
						<ChevronRight v-if="expandedSection !== 'medications'" :size="16" />
						<ChevronDown v-else :size="16" />
						{{ t('nextdiary', 'Medications') }}
					</h4>
					<template v-if="expandedSection === 'medications'">
						<input v-if="searchOpen === 'medications'"
							ref="searchMedications"
							v-model="medicationSearchQuery"
							type="text"
							class="sidebar-search-input"
							:placeholder="t('nextdiary', 'Search...')">
						<MedicationCloud :medications="filteredMedications" :active-medication-id="activeMedicationId" @select-medication="onMobileMedicationSelect" />
					</template>
				</div>
			</template>
		</div>
		<div v-if="mobileSidebarOpen"
			class="mobile-sidebar-backdrop"
			@click="mobileSidebarOpen = false" />
		<button class="mobile-sidebar-fab"
			:aria-label="t('nextdiary', 'Tags')"
			@click="mobileSidebarOpen = !mobileSidebarOpen">
			<TagMultiple :size="20" />
		</button>
	</NcContent>
</template>

<script>
import {
	NcAppContent,
	NcAppNavigation,
	NcContent,
	NcAppNavigationItem,
	NcDatetimePicker,
	NcButton,
	NcActionLink,
	NcAppNavigationIconBullet,
	NcListItem,
} from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox'
import Markdown from 'vue-material-design-icons/LanguageMarkdown'
import { generateUrl } from '@nextcloud/router'
import TagCloud from './TagCloud.vue'
import SymptomCloud from './SymptomCloud.vue'
import MedicationCloud from './MedicationCloud.vue'
import TagMultiple from 'vue-material-design-icons/TagMultiple'
import ChevronDown from 'vue-material-design-icons/ChevronDown'
import ChevronRight from 'vue-material-design-icons/ChevronRight'
import axios from '@nextcloud/axios'

export default {
	name: 'Diary',
	components: {
		NcAppNavigation,
		NcContent,
		NcAppContent,
		NcAppNavigationItem,
		NcDatetimePicker,
		NcButton,
		FilePdfBox,
		Markdown,
		NcActionLink,
		NcAppNavigationIconBullet,
		NcListItem,
		TagCloud,
		SymptomCloud,
		MedicationCloud,
		TagMultiple,
		ChevronDown,
		ChevronRight,
	},
	data() {
		const baseUrl = generateUrl('apps/nextdiary')
		return {
			selectedDate: null,
			calendarOpen: false,
			baseUrl,
			pastEntriesAmount: 10,
			lastEntries: [],
			entryDates: [],
			calendarObserver: null,
			calendarViewDate: null,
			tags: [],
			tagSearchQuery: '',
			symptomSearchQuery: '',
			medicationSearchQuery: '',
			searchOpen: null,
			symptoms: [],
			medications: [],
			settings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
			},
			mobileSidebarOpen: false,
			expandedSection: null,
			sidebarOrder: ['tags', 'symptoms', 'medications'],
		}
	},
	computed: {
		currentDate() {
			// Extract date from current route
			if (this.$route.name === 'day') {
				return this.$route.params.date
			}
			return moment().format('YYYY-MM-DD')
		},
		formattedDate() {
			return moment(this.currentDate).format('LL')
		},
		showNextDayButton() {
			const nextDay = moment(this.currentDate).add(1, 'day')
			const today = moment()
			return nextDay.isBefore(today)
		},
		markdownDownloadLink() {
			return this.baseUrl + '/export/markdown'
		},
		pdfDownloadLink() {
			return this.baseUrl + '/export/pdf'
		},
		filteredTags() {
			if (!this.tagSearchQuery) return this.tags
			const q = this.tagSearchQuery.toLowerCase()
			return this.tags.filter(tag => tag.name.toLowerCase().includes(q))
		},
		filteredSymptoms() {
			if (!this.symptomSearchQuery) return this.symptoms
			const q = this.symptomSearchQuery.toLowerCase()
			return this.symptoms.filter(s => s.name.toLowerCase().includes(q))
		},
		filteredMedications() {
			if (!this.medicationSearchQuery) return this.medications
			const q = this.medicationSearchQuery.toLowerCase()
			return this.medications.filter(m => m.name.toLowerCase().includes(q))
		},
		activeTagId() {
			if (this.$route.name === 'tag-entries') {
				return parseInt(this.$route.params.tagId)
			}
			return null
		},
		activeSymptomId() {
			if (this.$route.name === 'symptom-entries') {
				return parseInt(this.$route.params.symptomId)
			}
			return null
		},
		activeMedicationId() {
			if (this.$route.name === 'medication-entries') {
				return parseInt(this.$route.params.medicationId)
			}
			return null
		},
		entryDatesSet() {
			return new Set(this.entryDates)
		},
		entryMonthsSet() {
			const months = new Set()
			this.entryDates.forEach(d => months.add(d.substring(0, 7)))
			return months
		},
		entryYearsSet() {
			const years = new Set()
			this.entryDates.forEach(d => years.add(d.substring(0, 4)))
			return years
		},
	},
	watch: {
		'$route.params'() {
			this.fetchPastEntries()
		},
	},
	mounted() {
		this.fetchPastEntries()
		this.fetchEntryDates()
		this.fetchTags()
		this.fetchSymptoms()
		this.fetchMedications()
		this.fetchSettings()
	},
	beforeDestroy() {
		this.disconnectObserver()
	},
	methods: {
		onDateChange(date) {
			const targetDate = moment(date).format('YYYY-MM-DD')
			if (this.currentDate !== targetDate || this.$route.name !== 'day') {
				this.$router.push({ name: 'day', params: { date: targetDate } })
			}
			this.calendarOpen = false
		},
		goToEntry(entry) {
			this.$router.push({ name: 'entry', params: { id: String(entry.id) } })
		},
		isActiveEntry(entry) {
			if (this.$route.name === 'entry') {
				return String(entry.id) === this.$route.params.id
			}
			if (this.$route.name === 'day') {
				return entry.date === this.$route.params.date
			}
			return false
		},
		openCalendar() {
			this.calendarOpen = !this.calendarOpen
			if (this.calendarOpen) {
				this.$nextTick(() => {
					setTimeout(() => {
						this.applyHighlights()
						this.observeCalendar()
					}, 150)
				})
			} else {
				this.disconnectObserver()
			}
		},
		onCalendarChange(date) {
			this.calendarViewDate = date
			if (this.calendarOpen) {
				this.$nextTick(() => {
					this.applyHighlights()
				})
			}
		},
		onCalendarPanelUpdate() {
			if (this.calendarOpen) {
				this.$nextTick(() => {
					this.applyHighlights()
				})
			}
		},
		goPrevDay() {
			const yesterday = moment(this.currentDate).subtract(1, 'day')
			this.$router.push({ name: 'day', params: { date: yesterday.format('YYYY-MM-DD') } })
		},
		goNextDay() {
			const tomorrow = moment(this.currentDate).add(1, 'day')
			this.$router.push({ name: 'day', params: { date: tomorrow.format('YYYY-MM-DD') } })
		},
		onEntryChanged() {
			this.fetchPastEntries()
			this.fetchEntryDates()
			this.fetchTags()
			this.fetchSymptoms()
			this.fetchMedications()
		},
		selectTag(tagId) {
			this.$router.push({ name: 'tag-entries', params: { tagId: String(tagId) } })
		},
		selectSymptom(symptomId) {
			this.$router.push({ name: 'symptom-entries', params: { symptomId: String(symptomId) } })
		},
		onMobileTagSelect(tagId) {
			this.mobileSidebarOpen = false
			this.selectTag(tagId)
		},
		onMobileSymptomSelect(symptomId) {
			this.mobileSidebarOpen = false
			this.selectSymptom(symptomId)
		},
		selectMedication(medicationId) {
			this.$router.push({ name: 'medication-entries', params: { medicationId: String(medicationId) } })
		},
		onMobileMedicationSelect(medicationId) {
			this.mobileSidebarOpen = false
			this.selectMedication(medicationId)
		},
		toggleSection(sectionKey) {
			if (this.expandedSection === sectionKey) {
				this.toggleSearch(sectionKey)
			} else {
				this.expandedSection = sectionKey
				this.searchOpen = null
				this.tagSearchQuery = ''
				this.symptomSearchQuery = ''
				this.medicationSearchQuery = ''
			}
		},
		toggleSearch(section) {
			if (this.searchOpen === section) {
				this.searchOpen = null
				this.tagSearchQuery = ''
				this.symptomSearchQuery = ''
				this.medicationSearchQuery = ''
			} else {
				this.searchOpen = section
				this.$nextTick(() => {
					const refName = 'search' + section.charAt(0).toUpperCase() + section.slice(1)
					if (this.$refs[refName]) {
						this.$refs[refName].focus()
					}
				})
			}
		},
		stripMarkdown(text) {
			if (!text) return ''
			return text
				.replace(/^#{1,6}\s+/gm, '')
				.replace(/\*\*(.+?)\*\*/g, '$1')
				.replace(/\*(.+?)\*/g, '$1')
				.replace(/~~(.+?)~~/g, '$1')
				.replace(/^\s*[-*+]\s+/gm, '')
				.replace(/^\s*>\s+/gm, '')
				.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
				.trim()
		},
		formatEntryTitle(entry) {
			const date = moment(entry.date).format('D MMM')
			if (entry.createdAt) {
				const time = moment(entry.createdAt).format('HH:mm')
				return `${date} ${time}`
			}
			return date
		},
		fetchPastEntries() {
			axios.get(generateUrl('apps/nextdiary/api/last-entries/' + this.pastEntriesAmount))
				.then(response => {
					if (response.data) {
						this.lastEntries = response.data
					}
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.log(error)
				})
		},
		fetchTags() {
			axios.get(generateUrl('apps/nextdiary/api/tags'))
				.then(response => {
					this.tags = response.data || []
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching tags:', error)
				})
		},
		fetchSymptoms() {
			axios.get(generateUrl('apps/nextdiary/api/symptoms'))
				.then(response => {
					this.symptoms = response.data || []
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching symptoms:', error)
				})
		},
		fetchMedications() {
			axios.get(generateUrl('apps/nextdiary/api/medications'))
				.then(response => {
					this.medications = response.data || []
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching medications:', error)
				})
		},
		async fetchSettings() {
			try {
				const response = await axios.get(generateUrl('/apps/nextdiary/api/settings'))
				if (response.data) {
					this.settings = { ...this.settings, ...response.data }
					if (Array.isArray(response.data.sidebar_order)) {
						this.sidebarOrder = response.data.sidebar_order
					}
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error fetching settings:', error)
			}
		},
		fetchEntryDates() {
			axios.get(generateUrl('apps/nextdiary/api/entry-dates'))
				.then(response => {
					if (response.data) {
						this.entryDates = response.data
						if (this.calendarOpen) {
							this.$nextTick(() => this.applyHighlights())
						}
					}
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching entry dates:', error)
				})
		},
		findCalendarPopup() {
			return document.querySelector('.mx-datepicker-main.mx-datepicker-popup')
				|| document.querySelector('.mx-datepicker-content')
		},
		observeCalendar() {
			this.disconnectObserver()
			const popup = this.findCalendarPopup()
			if (!popup) return

			let debounce = null
			this.calendarObserver = new MutationObserver(() => {
				clearTimeout(debounce)
				debounce = setTimeout(() => this.applyHighlights(), 80)
			})
			this.calendarObserver.observe(popup, {
				childList: true,
				subtree: true,
			})
		},
		disconnectObserver() {
			if (this.calendarObserver) {
				this.calendarObserver.disconnect()
				this.calendarObserver = null
			}
		},
		applyHighlights() {
			if (this.calendarObserver) {
				this.calendarObserver.disconnect()
			}

			const panel = document.querySelector('.mx-calendar')
			if (panel) {
				if (panel.classList.contains('mx-calendar-panel-month')) {
					this.highlightMonths()
				} else if (panel.classList.contains('mx-calendar-panel-year')) {
					this.highlightYears()
				} else {
					this.highlightDates()
				}
			}

			const popup = this.findCalendarPopup()
			if (this.calendarObserver && popup) {
				this.calendarObserver.observe(popup, {
					childList: true,
					subtree: true,
				})
			}
		},
		highlightDates() {
			const viewDate = this.calendarViewDate || new Date(this.currentDate)
			const year = viewDate.getFullYear()
			const monthIndex = viewDate.getMonth()

			const cells = document.querySelectorAll('.mx-calendar-content .cell')
			cells.forEach(cell => {
				cell.classList.remove('has-diary-entry')
				if (cell.classList.contains('not-current-month')) return

				const day = parseInt(cell.textContent.trim())
				if (isNaN(day)) return

				const mm = String(monthIndex + 1).padStart(2, '0')
				const dd = String(day).padStart(2, '0')
				if (this.entryDatesSet.has(`${year}-${mm}-${dd}`)) {
					cell.classList.add('has-diary-entry')
				}
			})
		},
		highlightMonths() {
			const viewDate = this.calendarViewDate || new Date(this.currentDate)
			const year = viewDate.getFullYear()

			const cells = document.querySelectorAll('.mx-table-month .cell')
			cells.forEach((cell, index) => {
				cell.classList.remove('has-diary-entry')
				const mm = String(index + 1).padStart(2, '0')
				if (this.entryMonthsSet.has(`${year}-${mm}`)) {
					cell.classList.add('has-diary-entry')
				}
			})
		},
		highlightYears() {
			const cells = document.querySelectorAll('.mx-table-year .cell')
			cells.forEach(cell => {
				cell.classList.remove('has-diary-entry')
				const year = cell.textContent.trim()
				if (this.entryYearsSet.has(year)) {
					cell.classList.add('has-diary-entry')
				}
			})
		},
	},
}
</script>

<style lang="scss">
#nextdiary-content {
	margin: 0;
	height: calc(100% - 50px);
	width: inherit;

	.app-content {
		max-width: none !important;
	}

	#nextdiary-right-sidebar {
		width: 250px;
		min-width: 250px;
		border-left: 1px solid var(--color-border);
		overflow-y: auto;
		height: 100%;

		@media (max-width: 768px) {
			position: fixed;
			top: 50px;
			right: 0;
			bottom: 0;
			width: 280px;
			min-width: 280px;
			z-index: 10001;
			background: var(--color-main-background);
			border-left: 1px solid var(--color-border);
			transform: translateX(100%);
			transition: transform 0.25s ease-in-out;

			&.mobile-open {
				transform: translateX(0);
			}
		}

		.sidebar-section {
			border-bottom: 1px solid var(--color-border);
			padding-bottom: 0;

			&:last-child {
				border-bottom: none;
			}

			&.expanded {
				padding-bottom: 4px;
			}
		}

		.sidebar-title {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
			color: #fff;
			padding: 12px;
			margin: 0;
			cursor: pointer;
			user-select: none;

			&:hover {
				opacity: 0.8;
			}
		}

		.sidebar-search-input {
			width: calc(100% - 24px);
			margin: 8px 12px 0;
			padding: 6px 10px;
			border: 1px solid var(--color-border);
			border-radius: 16px;
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			font-size: 13px;
			outline: none;
			box-sizing: border-box;

			&:focus {
				border-color: var(--color-primary);
			}
		}
	}

	.navigation-wrapper {
		display: flex;
		justify-content: space-around;
		padding: 12px;

		.diary-datetimepicker {
			width: 0;
			.mx-input-wrapper {
				display: none;
			}
		}

		.open-calendar {
			flex-grow: 3;
			font-size: 14px;
		}
	}

	.export {
		padding: 12px;
	}
}

.mobile-sidebar-backdrop {
	display: none;
}

.mobile-sidebar-fab {
	display: none;
}

@media (max-width: 768px) {
	.mobile-sidebar-backdrop {
		display: block;
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 10000;
		background: rgba(0, 0, 0, 0.4);
	}

	.mobile-sidebar-fab {
		display: flex;
		align-items: center;
		justify-content: center;
		position: fixed;
		bottom: calc(16px + env(safe-area-inset-bottom, 0px));
		right: 16px;
		z-index: 9999;
		width: 48px;
		height: 48px;
		border-radius: 50%;
		border: none;
		background: var(--color-primary);
		color: #fff;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
		cursor: pointer;

		&:active {
			opacity: 0.8;
		}
	}

}

.mx-datepicker-popup .mx-calendar {
	width: 300px !important;
	margin: 0 auto;
}

.mx-calendar-content .cell.has-diary-entry {
	position: relative !important;

	&::before {
		content: '' !important;
		position: absolute !important;
		bottom: 2px !important;
		left: 50% !important;
		transform: translateX(-50%) !important;
		width: 6px !important;
		height: 6px !important;
		background-color: #46ba61 !important;
		border-radius: 50% !important;
		display: block !important;
		z-index: 10 !important;
	}
}

.mx-calendar-content .cell.has-diary-entry.active::before,
.mx-calendar-content .cell.has-diary-entry:hover::before {
	background-color: #46ba61 !important;
}
</style>

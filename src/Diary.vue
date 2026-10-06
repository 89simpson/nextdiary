<template>
	<NcContent id="nextdiary-content" app-name="nextdiary">
		<NcAppNavigation :aria-label="t('nextdiary', 'Diary')">
			<div class="navigation-wrapper">
				<NcButton class="icon icon-view-previous"
					:aria-label="t('nextdiary', 'Previous day')"
					@click="goPrevDay" />
				<NcButton ref="calendarButton"
					class="open-calendar"
					@click="openCalendar">
					{{ formattedDate }}
				</NcButton>
				<NcButton v-if="showNextDayButton"
					class="icon icon-view-next"
					:aria-label="t('nextdiary', 'Next day')"
					@click="goNextDay" />
			</div>
			<Teleport to="body">
				<div v-if="calendarOpen"
					ref="calendarPopup"
					class="diary-calendar-popup"
					:style="calendarPopupStyle">
					<NcDateTimePicker :model-value="selectedDate"
						type="date"
						inline
						@update:model-value="onCalendarSelect" />
				</div>
			</Teleport>
			<template #list>
				<ul>
					<NcListItem v-for="entry in lastEntries"
						:key="entry.id"
						:name="formatEntryTitle(entry)"
						:bold="false"
						:compact="true"
						counter-type="highlighted"
						@click="goToEntry(entry)">
						<template #icon>
							<NcAppNavigationIconBullet v-if="isActiveEntry(entry)" color="0082c9" />
							<NcAppNavigationIconBullet v-else color="FFFFFF" />
						</template>
						<template #subname>
							{{ stripMarkdown(entry.excerpt) }}
						</template>
					</NcListItem>
				</ul>
			</template>
			<template #footer>
				<NcAppNavigationItem class="export"
					:name="t('nextdiary', 'Export')"
					icon="icon-download"
					@click="showExportDialog = true" />
			</template>
		</NcAppNavigation>
		<ExportDialog v-if="showExportDialog"
			:current-date="currentDate"
			@close="showExportDialog = false" />
		<NcAppContent>
			<router-view @entry-changed="onEntryChanged"
				@navigate-date="onDateChange" />
		</NcAppContent>
		<div id="nextdiary-right-sidebar" :class="{ 'mobile-open': mobileSidebarOpen }">
			<template v-for="sectionKey in sidebarOrder" :key="sectionKey">
				<div v-if="sectionKey === 'tags' && settings.show_tags"
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
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationIconBullet from '@nextcloud/vue/components/NcAppNavigationIconBullet'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { apiUrl, validId } from './apiUrl.js'
import TagCloud from './TagCloud.vue'
import ExportDialog from './ExportDialog.vue'
import SymptomCloud from './SymptomCloud.vue'
import MedicationCloud from './MedicationCloud.vue'
import TagMultiple from 'vue-material-design-icons/TagMultiple'
import ChevronDown from 'vue-material-design-icons/ChevronDown'
import ChevronRight from 'vue-material-design-icons/ChevronRight'
import axios from '@nextcloud/axios'

const CALENDAR_OBSERVER_OPTIONS = {
	childList: true,
	subtree: true,
	attributes: true,
	attributeFilter: ['id', 'class'],
}

export default {
	name: 'Diary',
	components: {
		NcAppNavigation,
		NcContent,
		NcAppContent,
		NcAppNavigationItem,
		NcDateTimePicker,
		NcButton,
		NcAppNavigationIconBullet,
		ExportDialog,
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
			calendarPopupStyle: {},
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
			showExportDialog: false,
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
	beforeUnmount() {
		this.disconnectObserver()
		window.removeEventListener('resize', this.positionCalendar)
	},
	methods: {
		onDateChange(date) {
			const targetDate = moment(date).format('YYYY-MM-DD')
			if (this.currentDate !== targetDate || this.$route.name !== 'day') {
				this.$router.push({ name: 'day', params: { date: targetDate } })
			}
			this.closeCalendar()
		},
		onCalendarSelect(date) {
			if (!date) return
			this.selectedDate = date
			this.onDateChange(date)
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
			if (this.calendarOpen) {
				this.closeCalendar()
				return
			}
			this.positionCalendar()
			this.calendarOpen = true
			window.addEventListener('resize', this.positionCalendar)
			this.$nextTick(() => {
				setTimeout(() => {
					this.applyHighlights()
					this.observeCalendar()
				}, 150)
			})
		},
		closeCalendar() {
			this.calendarOpen = false
			this.disconnectObserver()
			window.removeEventListener('resize', this.positionCalendar)
		},
		positionCalendar() {
			// The calendar is teleported to <body> (like the former date picker popup),
			// so it is not clipped by the scrolling navigation; place it under the date button.
			const button = this.$refs.calendarButton?.$el
			if (!button) return
			const rect = button.getBoundingClientRect()
			const width = 300
			const left = Math.max(4, Math.min(rect.left, window.innerWidth - width - 4))
			this.calendarPopupStyle = {
				top: `${Math.round(rect.bottom + 2)}px`,
				left: `${Math.round(left)}px`,
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
					// refs inside v-for are collected into arrays in Vue 3
					const input = [this.$refs[refName]].flat()[0]
					if (input) {
						input.focus()
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
			axios.get(apiUrl('/last-entries/{amount}', { amount: validId(this.pastEntriesAmount) }))
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
			return this.$refs.calendarPopup || null
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
			this.calendarObserver.observe(popup, CALENDAR_OBSERVER_OPTIONS)
		},
		disconnectObserver() {
			if (this.calendarObserver) {
				this.calendarObserver.disconnect()
				this.calendarObserver = null
			}
		},
		applyHighlights() {
			const popup = this.findCalendarPopup()
			if (!popup) return

			if (this.calendarObserver) {
				this.calendarObserver.disconnect()
			}

			this.highlightDates(popup)
			this.highlightOverlay(popup)

			if (this.calendarObserver) {
				this.calendarObserver.observe(popup, CALENDAR_OBSERVER_OPTIONS)
			}
		},
		highlightDates(popup) {
			// Day cells carry their date in the id: "dp-YYYY-MM-DD"
			popup.querySelectorAll('.dp__calendar_item').forEach(cell => {
				const inner = cell.querySelector('.dp__cell_inner')
				const date = (cell.id || '').replace(/^dp-/, '')
				const hasEntry = !!inner
					&& !inner.classList.contains('dp__cell_offset')
					&& this.entryDatesSet.has(date)
				cell.classList.toggle('has-diary-entry', hasEntry)
			})
		},
		highlightOverlay(popup) {
			// Month / year selection overlays
			const cells = Array.from(popup.querySelectorAll('.dp__overlay [role="gridcell"]'))
			if (cells.length === 0) return
			const texts = cells.map(cell => cell.textContent.trim())
			if (texts.every(text => /^\d{4}$/.test(text))) {
				cells.forEach((cell, index) => {
					cell.classList.toggle('has-diary-entry', this.entryYearsSet.has(texts[index]))
				})
				return
			}
			if (cells.length !== 12) return
			const yearButton = popup.querySelector('[data-test-id^="year-mode-btn"]')
			const year = yearButton ? parseInt(yearButton.textContent.trim()) : NaN
			cells.forEach((cell, index) => {
				const mm = String(index + 1).padStart(2, '0')
				cell.classList.toggle('has-diary-entry', !isNaN(year) && this.entryMonthsSet.has(`${year}-${mm}`))
			})
		},
	},
}
</script>

<style lang="scss">
// Vue 3 mounts inside the #vue-content placeholder instead of replacing it:
// keep it out of the layout so the app root still sizes against #content.
#vue-content {
	display: contents;
	width: inherit;
	height: inherit;
}

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
			color: var(--color-main-text);
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

@media (max-width: 1024px) {
	.app-navigation:not(.app-navigation--close):not(.app-navigation--closed) ~ .app-content .day-header > .button-vue {
		visibility: hidden;
	}

	.app-navigation:not(.app-navigation--close):not(.app-navigation--closed) ~ .app-content {
		visibility: hidden !important;
	}
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

.diary-calendar-popup {
	position: fixed;
	z-index: 2000;
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 8px var(--color-box-shadow);

	// 300px wide like the former date picker popup
	.dp__main {
		--dp-menu-min-width: 300px;
	}

	.dp__calendar_item.has-diary-entry .dp__cell_inner,
	.dp__overlay [role="gridcell"].has-diary-entry > div {
		position: relative;

		&::after {
			content: '';
			position: absolute;
			bottom: 2px;
			left: 50%;
			transform: translateX(-50%);
			width: 6px;
			height: 6px;
			background-color: #46ba61;
			border-radius: 50%;
			display: block;
			z-index: 10;
		}
	}
}
</style>

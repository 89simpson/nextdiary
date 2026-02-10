<template>
	<NcContent id="nextdiary-content" app-name="nextdiary">
		<NcAppNavigation>
			<div class="navigation-wrapper">
				<NcButton class="icon icon-view-previous"
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
					@click="goNextDay" />
			</div>
			<template #list>
				<ul>
					<NcListItem v-for="entry in lastEntries"
						:key="entry.date"
						:title="formatDate(entry.date)"
						:bold="false"
						:compact="true"
						counter-type="highlighted"
						@click="!isCurrentDate(entry.date) ? onDateChange(entry.date) : null">
						<template #icon>
							<NcAppNavigationIconBullet v-if="isCurrentDate(entry.date)" color="0082c9" />
							<NcAppNavigationIconBullet v-else color="FFFFFF" />
						</template>
						<template #subtitle>
							{{ entry.excerpt }}
						</template>
					</NcListItem>
				</ul>
			</template>
			<template #footer>
				<NcAppNavigationItem class="export" :title="t('nextdiary', 'Export')" icon="icon-download">
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
			<Editor :date="date" @entry-edit="onEdit" />
		</NcAppContent>
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
import Editor from './Editor'
import moment from '@nextcloud/moment'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox'
import Markdown from 'vue-material-design-icons/LanguageMarkdown'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'Diary',
	components: {
		NcAppNavigation,
		NcContent,
		Editor,
		NcAppContent,
		NcAppNavigationItem,
		NcDatetimePicker,
		NcButton,
		FilePdfBox,
		Markdown,
		NcActionLink,
		NcAppNavigationIconBullet,
		NcListItem,
	},
	props: {
		date: {
			type: String,
			required: true,
		},
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
		}
	},
	computed: {
		formattedDate() {
			return this.formatDate(this.date)
		},
		showNextDayButton() {
			const nextDay = moment(this.date).add(1, 'day')
			const today = moment()
			return nextDay.isBefore(today)
		},
		markdownDownloadLink() {
			return this.baseUrl + '/export/markdown'
		},
		pdfDownloadLink() {
			return this.baseUrl + '/export/pdf'
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
	mounted() {
		this.fetchPastEntries()
		this.fetchEntryDates()
	},
	beforeDestroy() {
		this.disconnectObserver()
	},
	methods: {
		onDateChange(date) {
			this.$router.push({ name: 'date', params: { date: moment(date).format('YYYY-MM-DD') } })
			this.calendarOpen = false
			this.fetchPastEntries()
		},
		isCurrentDate(date) {
			return this.date === date
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
			// calendar-change emits a native Date object for the currently displayed month
			this.calendarViewDate = date
			if (this.calendarOpen) {
				this.$nextTick(() => {
					this.applyHighlights()
				})
			}
		},
		onCalendarPanelUpdate() {
			// Fired by panel-change (date/month/year switch)
			if (this.calendarOpen) {
				this.$nextTick(() => {
					this.applyHighlights()
				})
			}
		},
		goPrevDay() {
			const yesterday = moment(this.date).subtract(1, 'day')
			this.$router.push({ name: 'date', params: { date: yesterday.format('YYYY-MM-DD') } })
			this.fetchPastEntries()
		},
		goNextDay() {
			const tomorrow = moment(this.date).add(1, 'day')
			this.$router.push({ name: 'date', params: { date: tomorrow.format('YYYY-MM-DD') } })
			this.fetchPastEntries()
		},
		onEdit(date, content) {
			const entryIndex = this.lastEntries.findIndex((e) => e.date === date)
			if (entryIndex === -1) {
				this.lastEntries.unshift({ date, excerpt: content })
			} else {
				if (content) {
					this.lastEntries[entryIndex].excerpt = content.substring(0, 40)
				} else {
					this.lastEntries.splice(entryIndex, 1)
				}
			}
			// Update entry dates when an entry is added or deleted
			this.fetchEntryDates()
		},
		formatDate(date) {
			return moment(date).format('LL')
		},
		fetchPastEntries() {
			axios.get(generateUrl('apps/nextdiary/entries/' + this.pastEntriesAmount))
				.then(response => {
					if (response.data) {
						this.lastEntries = response.data
					} else {
						this.content = ''
					}
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.log(error)
					this.status = 'error'
				})
		},
		fetchEntryDates() {
			axios.get(generateUrl('apps/nextdiary/entry-dates'))
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
			// Body-appended popup (appendToBody: true) or inline content
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
			// Pause observer to avoid self-triggering from our class changes
			if (this.calendarObserver) {
				this.calendarObserver.disconnect()
			}

			// Detect current panel type via panel class on .mx-calendar element
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

			// Resume observer
			const popup = this.findCalendarPopup()
			if (this.calendarObserver && popup) {
				this.calendarObserver.observe(popup, {
					childList: true,
					subtree: true,
				})
			}
		},
		highlightDates() {
			// Use the Date object captured from calendar-change event
			const viewDate = this.calendarViewDate || new Date(this.date)
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
			const viewDate = this.calendarViewDate || new Date(this.date)
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

	.navigation-wrapper {
		display: flex;
		justify-content: space-around;
		padding: 12px;

		.diary-datetimepicker {
			width: 0; // Hides drop-down
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

// Highlight calendar cells with diary entries (works both inside component and body-appended popup)
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

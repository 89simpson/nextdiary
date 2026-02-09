<template>
	<NcContent id="nextdiary-content" app-name="nextdiary">
		<NcAppNavigation>
			<div class="navigation-wrapper">
				<NcButton class="icon icon-view-previous"
					@click="goPrevDay" />
				<NcDatetimePicker v-model="selectedDate"
					class="diary-datetimepicker"
					type="date"
					:open="calendarOpen"
					@change="onDateChange" />
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
			highlightInterval: null,
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
	},
	mounted() {
		this.fetchPastEntries()
		this.fetchEntryDates()
	},
	beforeDestroy() {
		// Clean up interval when component is destroyed
		if (this.highlightInterval) {
			clearInterval(this.highlightInterval)
		}
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
				// Update highlights when calendar is opened
				this.$nextTick(() => {
					setTimeout(() => {
						this.updateCalendarHighlights()
					}, 100)
				})
				// Continuously update highlights while calendar is open (handles month navigation)
				this.highlightInterval = setInterval(() => {
					this.updateCalendarHighlights()
				}, 300)
			} else {
				// Clear interval when calendar closes
				if (this.highlightInterval) {
					clearInterval(this.highlightInterval)
					this.highlightInterval = null
				}
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
			console.log('[NextDiary] Fetching entry dates from API...')
			axios.get(generateUrl('apps/nextdiary/entry-dates'))
				.then(response => {
					console.log('[NextDiary] API Response:', response.data)
					if (response.data) {
						this.entryDates = response.data
						console.log('[NextDiary] Entry dates loaded:', this.entryDates)
						this.updateCalendarHighlights()
					}
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching entry dates:', error)
				})
		},
		updateCalendarHighlights() {
			// Wait for DOM to be ready
			this.$nextTick(() => {
				setTimeout(() => {
					console.log('[NextDiary] updateCalendarHighlights called')
					const entryDatesSet = new Set(this.entryDates)
					const calendarCells = document.querySelectorAll('.mx-calendar-content .cell')

					console.log('[NextDiary] Calendar cells found:', calendarCells.length)
					console.log('[NextDiary] Entry dates to highlight:', Array.from(entryDatesSet))

					if (calendarCells.length === 0) {
						console.warn('[NextDiary] No calendar cells found, skipping highlight')
						return
					}

					// Get the currently displayed month/year from selectedDate or current date
					const displayDate = this.selectedDate ? new Date(this.selectedDate) : new Date(this.date)
					const displayYear = displayDate.getFullYear()
					const displayMonth = displayDate.getMonth() // 0-indexed

					console.log('[NextDiary] Display date:', displayYear, 'year,', displayMonth + 1, 'month')

					let highlightedCount = 0
					calendarCells.forEach(cell => {
						// Remove previous highlighting
						cell.classList.remove('has-diary-entry')

						// Get day number from cell text
						const dayText = cell.textContent.trim()
						const dayNumber = parseInt(dayText, 10)

						if (isNaN(dayNumber)) {
							return
						}

						// Skip cells that are not in the current month (grayed out dates)
						if (cell.classList.contains('not-current-month')) {
							return
						}

						// Construct the date string in YYYY-MM-DD format
						const monthStr = String(displayMonth + 1).padStart(2, '0')
						const dayStr = String(dayNumber).padStart(2, '0')
						const dateStr = `${displayYear}-${monthStr}-${dayStr}`

						// Check if this date has an entry
						if (entryDatesSet.has(dateStr)) {
							cell.classList.add('has-diary-entry')
							highlightedCount++
							console.log('[NextDiary] Highlighted date:', dateStr, 'for day cell:', dayNumber)
						}
					})

					console.log('[NextDiary] Total dates highlighted:', highlightedCount)
				}, 100)
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

// Highlight dates with diary entries
::v-deep .mx-calendar-content {
	.cell.has-diary-entry {
		position: relative;

		&::before {
			content: '';
			position: absolute;
			bottom: 2px;
			left: 50%;
			transform: translateX(-50%);
			width: 6px;
			height: 6px;
			background-color: #46ba61;
			border-radius: 50%;
		}
	}

	// Make sure the highlight is visible on active/hover states
	.cell.has-diary-entry.active::before,
	.cell.has-diary-entry:hover::before {
		background-color: #46ba61;
	}
}
</style>

<template>
	<div class="symptom-entries-view">
		<div class="symptom-header">
			<NcButton type="tertiary"
				:aria-label="t('nextdiary', 'Back')"
				@click="goBack">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>
			<h2>{{ symptomName }}</h2>
		</div>
		<div v-if="isLoading" class="symptom-loading">
			<i class="fa fa-spinner fa-spin fa-3x" />
		</div>
		<div v-else-if="entries.length > 0" class="symptom-entries">
			<div v-for="entry in entries"
				:key="entry.id"
				class="entry-card"
				@click="openEntry(entry)">
				<div class="entry-card-header">
					<span class="entry-date">{{ formatDate(entry.entryDate) }}</span>
					<span v-if="entry.createdAt" class="entry-time">{{ formatTime(entry.createdAt) }}</span>
					<span v-if="entry.entryRatings && entry.entryRatings.mood" class="entry-mood">
						{{ moodEmoji(entry.entryRatings.mood) }}
					</span>
				</div>
				<div class="entry-preview">
					{{ getExcerpt(entry) }}
				</div>
				<div v-if="entry.symptoms && entry.symptoms.length" class="entry-symptoms">
					<span v-for="s in entry.symptoms" :key="s.id" class="symptom-badge">
						{{ s.name }}
					</span>
				</div>
			</div>
		</div>
		<NcEmptyContent v-else
			:name="t('nextdiary', 'No entries with this symptom')">
			<template #icon>
				<HeartPulse :size="64" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft'
import HeartPulse from 'vue-material-design-icons/HeartPulse'
import moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SymptomEntriesView',
	components: { NcButton, NcEmptyContent, ArrowLeft, HeartPulse },
	props: {
		symptomId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			entries: [],
			symptomName: '',
			isLoading: false,
			moodEmojis: ['\uD83D\uDE1E', '\uD83D\uDE15', '\uD83D\uDE10', '\uD83D\uDE42', '\uD83D\uDE0A'],
		}
	},
	watch: {
		symptomId() {
			this.fetchEntries()
		},
	},
	mounted() {
		this.fetchEntries()
	},
	methods: {
		fetchEntries() {
			this.isLoading = true
			axios.get(generateUrl('apps/nextdiary/api/entries/symptom/' + this.symptomId))
				.then(response => {
					this.entries = response.data || []
					if (this.entries.length > 0 && this.entries[0].symptoms) {
						const sym = this.entries[0].symptoms.find(s => s.id === parseInt(this.symptomId))
						if (sym) this.symptomName = sym.name
					}
					this.isLoading = false
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching symptom entries:', error)
					this.isLoading = false
				})
		},
		goBack() {
			this.$router.push({ name: 'day', params: { date: moment().format('YYYY-MM-DD') } })
		},
		openEntry(entry) {
			this.$router.push({ name: 'entry', params: { id: String(entry.id) } })
		},
		formatDate(date) {
			return moment(date).format('D MMM YYYY')
		},
		formatTime(createdAt) {
			if (!createdAt) return ''
			return moment(createdAt).format('HH:mm')
		},
		moodEmoji(level) {
			return this.moodEmojis[(level || 3) - 1] || ''
		},
		getExcerpt(entry) {
			const content = (entry.entryContent || '').trim()
			if (!content) return t('nextdiary', 'Empty entry')
			return content
				.replace(/^#{1,6}\s+/gm, '')
				.replace(/\*\*(.+?)\*\*/g, '$1')
				.replace(/\*(.+?)\*/g, '$1')
				.replace(/~~(.+?)~~/g, '$1')
				.replace(/^\s*[-*+]\s+/gm, '')
				.replace(/^\s*>\s+/gm, '')
				.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
				.trim()
		},
	},
}
</script>

<style lang="scss" scoped>
.symptom-entries-view {
	padding: 20px 50px;

	.symptom-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 20px;

		h2 {
			font-size: 20px;
			font-weight: 700;
			margin: 0;
			color: #ef9a9a;
		}
	}

	.symptom-loading {
		display: flex;
		justify-content: center;
		padding: 40px;
	}

	.symptom-entries {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.entry-card {
		border: 1px solid var(--color-border);
		border-radius: 8px;
		padding: 10px 12px;
		cursor: pointer;
		transition: background-color 0.2s;

		&:hover {
			background-color: var(--color-background-hover);
		}

		.entry-card-header {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 4px;

			.entry-date {
				font-weight: 600;
			}

			.entry-time {
				color: var(--color-text-lighter);
			}

			.entry-mood {
				font-size: 16px;
			}
		}

		.entry-preview {
			color: var(--color-text-lighter);
			word-break: break-word;
			line-height: 1.4;
			display: -webkit-box;
			-webkit-line-clamp: 3;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}

		.entry-symptoms {
			margin-top: 6px;
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}
	}
}

.symptom-badge {
	display: inline-block;
	padding: 2px 8px;
	background-color: #ef9a9a;
	color: #b71c1c;
	border-radius: 12px;
	font-size: 0.85em;
}

@media (max-width: 768px) {
	.symptom-entries-view {
		padding: 12px 10px 12px 44px;

		.symptom-header h2 {
			font-size: 16px;
		}

		.entry-card {
			padding: 12px 10px;

			.entry-preview {
				font-size: 14px;
				-webkit-line-clamp: 2;
			}
		}
	}
}
</style>

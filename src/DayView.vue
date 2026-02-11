<template>
	<div class="day-view">
		<div class="day-header">
			<h2>{{ formattedDate }}</h2>
			<NcButton type="primary" @click="createNewEntry">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('nextdiary', 'New entry') }}
			</NcButton>
		</div>
		<div v-if="isLoading" class="day-loading">
			<i class="fa fa-spinner fa-spin fa-3x" />
		</div>
		<div v-else-if="entries.length > 0" class="day-entries">
			<div v-for="entry in entries"
				:key="entry.id"
				class="entry-card"
				@click="openEntry(entry)">
				<div class="entry-card-header">
					<span class="entry-time">{{ formatTime(entry.createdAt) }}</span>
					<span v-if="entry.entryRatings && entry.entryRatings.mood" class="entry-mood">
						{{ moodEmoji(entry.entryRatings.mood) }}
					</span>
					<span v-if="entry.entryRatings && entry.entryRatings.wellbeing" class="entry-wellbeing">
						<span v-for="n in entry.entryRatings.wellbeing" :key="'wb-' + n" class="wb-dot active">&#9679;</span>
						<span v-for="n in (5 - entry.entryRatings.wellbeing)" :key="'wbe-' + n" class="wb-dot">&#9679;</span>
					</span>
					<NcActions>
						<NcActionButton @click.stop="confirmDelete(entry)">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('nextdiary', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
				<div class="entry-preview">
					{{ getExcerpt(entry) }}
				</div>
				<div class="entry-meta-badges">
					<template v-if="entry.tags && entry.tags.length">
						<span v-for="tag in entry.tags" :key="'tag-' + tag.id" class="tag-badge">
							#{{ tag.name }}
						</span>
					</template>
					<template v-if="entry.symptoms && entry.symptoms.length">
						<span v-for="s in entry.symptoms" :key="'sym-' + s.id" class="symptom-badge">
							{{ s.name }}
						</span>
					</template>
				</div>
			</div>
		</div>
		<NcEmptyContent v-else
			:name="t('nextdiary', 'No entries for this day')"
			:description="t('nextdiary', 'Click the button above to create a new entry')">
			<template #icon>
				<NoteEdit :size="64" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import {
	NcButton,
	NcActions,
	NcActionButton,
	NcEmptyContent,
} from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus'
import Delete from 'vue-material-design-icons/Delete'
import NoteEdit from 'vue-material-design-icons/NoteEditOutline'
import moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'DayView',
	components: {
		NcButton,
		NcActions,
		NcActionButton,
		NcEmptyContent,
		Plus,
		Delete,
		NoteEdit,
	},
	props: {
		date: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			entries: [],
			isLoading: false,
		}
	},
	computed: {
		formattedDate() {
			return moment(this.date).format('dddd, LL')
		},
	},
	watch: {
		date() {
			this.fetchEntries()
		},
	},
	mounted() {
		this.fetchEntries()
	},
	methods: {
		fetchEntries() {
			this.isLoading = true
			axios.get(generateUrl('apps/nextdiary/api/entries/' + this.date))
				.then(response => {
					this.entries = response.data || []
					this.isLoading = false
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching entries:', error)
					this.isLoading = false
				})
		},
		async createNewEntry() {
			try {
				const response = await axios.post(generateUrl('apps/nextdiary/api/entry/' + this.date), {
					content: '',
				})
				this.$emit('entry-changed')
				this.$router.push({ name: 'entry', params: { id: String(response.data.id) } })
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error creating entry:', error)
			}
		},
		openEntry(entry) {
			this.$router.push({ name: 'entry', params: { id: String(entry.id) } })
		},
		async confirmDelete(entry) {
			if (!confirm(t('nextdiary', 'Are you sure you want to delete this entry?'))) {
				return
			}
			try {
				await axios.delete(generateUrl('apps/nextdiary/api/entry/' + entry.id))
				this.entries = this.entries.filter(e => e.id !== entry.id)
				this.$emit('entry-changed')
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error deleting entry:', error)
			}
		},
		formatTime(createdAt) {
			if (!createdAt) return ''
			return moment(createdAt).format('HH:mm')
		},
		moodEmoji(level) {
			const emojis = ['\uD83D\uDE1E', '\uD83D\uDE15', '\uD83D\uDE10', '\uD83D\uDE42', '\uD83D\uDE0A']
			return emojis[(level || 3) - 1] || ''
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
.day-view {
	padding: 20px 50px;

	.day-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20px;

		h2 {
			font-size: 20px;
			font-weight: 700;
			margin: 0;
		}
	}

	.day-loading {
		display: flex;
		justify-content: center;
		padding: 40px;
	}

	.day-entries {
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
			justify-content: space-between;
			align-items: center;
			margin-bottom: 4px;

			.entry-time {
				font-weight: 600;
				color: var(--color-primary);
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

		.entry-mood {
			font-size: 18px;
		}

		.entry-wellbeing {
			display: inline-flex;
			gap: 1px;
			font-size: 10px;

			.wb-dot {
				color: var(--color-border-dark);

				&.active {
					color: var(--color-warning);
				}
			}
		}

		.entry-meta-badges {
			margin-top: 6px;
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}

		.tag-badge {
			display: inline-block;
			padding: 2px 8px;
			background-color: #a5d6a7;
			color: #1b5e20;
			border-radius: 12px;
			font-size: 0.85em;
		}

		.symptom-badge {
			display: inline-block;
			padding: 2px 8px;
			background-color: #ef9a9a;
			color: #b71c1c;
			border-radius: 12px;
			font-size: 0.85em;
		}
	}
}

@media (max-width: 500px) {
	.day-view {
		padding: 12px;
	}
}
</style>

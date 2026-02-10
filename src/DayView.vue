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
		getExcerpt(entry) {
			const content = (entry.entryContent || '').trim()
			if (content.length > 200) {
				return content.substring(0, 200) + '...'
			}
			return content || t('nextdiary', 'Empty entry')
		},
	},
}
</script>

<style lang="scss" scoped>
.day-view {
	padding: 20px 50px;
	max-width: 900px;

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
		}
	}
}

@media (max-width: 500px) {
	.day-view {
		padding: 12px;
	}
}
</style>

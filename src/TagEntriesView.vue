<template>
	<div class="tag-entries-view">
		<div class="tag-header">
			<NcButton type="tertiary"
				:aria-label="t('nextdiary', 'Back')"
				@click="goBack">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>
			<template v-if="isEditing">
				<input ref="renameInput"
					v-model="editName"
					type="text"
					class="rename-input"
					:disabled="isSaving"
					@keyup.enter="saveRename"
					@keyup.esc="cancelEdit">
				<NcButton type="tertiary"
					:aria-label="t('nextdiary', 'Save')"
					:disabled="isSaving"
					@click="saveRename">
					<template #icon>
						<Check :size="20" />
					</template>
				</NcButton>
				<NcButton type="tertiary"
					:aria-label="t('nextdiary', 'Cancel')"
					:disabled="isSaving"
					@click="cancelEdit">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</template>
			<template v-else>
				<h2>{{ tagName }}</h2>
				<NcButton v-if="tagName"
					type="tertiary"
					:aria-label="t('nextdiary', 'Rename')"
					@click="startEdit">
					<template #icon>
						<Pencil :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="tagName"
					type="tertiary"
					:aria-label="t('nextdiary', 'Delete')"
					@click="confirmDelete">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
			</template>
		</div>
		<p v-if="renameError" class="rename-error">
			{{ renameError }}
		</p>
		<div v-if="isLoading" class="tag-loading">
			<i class="fa fa-spinner fa-spin fa-3x" />
		</div>
		<div v-else-if="entries.length > 0" class="tag-entries">
			<div v-for="entry in entries"
				:key="entry.id"
				class="entry-card"
				@click="openEntry(entry)">
				<div class="entry-card-header">
					<span class="entry-date">{{ formatDate(entry.entryDate) }}</span>
					<span v-if="entry.createdAt" class="entry-time">{{ formatTime(entry.createdAt) }}</span>
				</div>
				<div class="entry-preview">
					{{ getExcerpt(entry) }}
				</div>
				<div v-if="entry.tags && entry.tags.length" class="entry-tags">
					<span v-for="tag in entry.tags" :key="tag.id" class="tag-badge">
						{{ tag.name }}
					</span>
				</div>
			</div>
		</div>
		<NcEmptyContent v-else
			:name="t('nextdiary', 'No entries with this tag')">
			<template #icon>
				<TagIcon :size="64" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft'
import TagIcon from 'vue-material-design-icons/Tag'
import Pencil from 'vue-material-design-icons/Pencil'
import Check from 'vue-material-design-icons/Check'
import Close from 'vue-material-design-icons/Close'
import Delete from 'vue-material-design-icons/Delete'
import moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'TagEntriesView',
	components: { NcButton, NcEmptyContent, ArrowLeft, TagIcon, Pencil, Check, Close, Delete },
	props: {
		tagId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			entries: [],
			tagName: '',
			isLoading: false,
			isEditing: false,
			isSaving: false,
			editName: '',
			renameError: '',
		}
	},
	watch: {
		tagId() {
			this.fetchEntries()
		},
	},
	mounted() {
		this.fetchEntries()
	},
	methods: {
		fetchEntries() {
			this.isLoading = true
			axios.get(generateUrl('apps/nextdiary/api/entries/tag/' + this.tagId))
				.then(response => {
					this.entries = response.data || []
					if (this.entries.length > 0 && this.entries[0].tags) {
						const tag = this.entries[0].tags.find(t => t.id === parseInt(this.tagId))
						if (tag) this.tagName = tag.name
					}
					this.isLoading = false
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching tag entries:', error)
					this.isLoading = false
				})
		},
		goBack() {
			this.$router.push({ name: 'day', params: { date: moment().format('YYYY-MM-DD') } })
		},
		startEdit() {
			this.editName = this.tagName
			this.renameError = ''
			this.isEditing = true
			this.$nextTick(() => {
				if (this.$refs.renameInput) {
					this.$refs.renameInput.focus()
					this.$refs.renameInput.select()
				}
			})
		},
		cancelEdit() {
			this.isEditing = false
			this.isSaving = false
			this.editName = ''
			this.renameError = ''
		},
		saveRename() {
			if (this.isSaving) return
			const newName = (this.editName || '').trim()
			this.isSaving = true
			this.renameError = ''
			axios.put(generateUrl('apps/nextdiary/api/tag/' + this.tagId), { name: newName })
				.then(response => {
					this.isSaving = false
					this.isEditing = false
					this.editName = ''
					this.$emit('entry-changed')
					const cloud = response.data || []
					const current = cloud.find(tag => tag.id === parseInt(this.tagId))
					if (current) {
						this.tagName = current.name
						this.fetchEntries()
						return
					}
					// The tag was merged into an existing one: follow it
					const merged = cloud.find(tag => tag.name.toLowerCase() === newName.toLowerCase())
					if (merged) {
						this.$router.push({ name: 'tag-entries', params: { tagId: String(merged.id) } })
					} else {
						this.tagName = newName
						this.fetchEntries()
					}
				})
				.catch(error => {
					this.isSaving = false
					this.renameError = t('nextdiary', 'Could not rename')
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error renaming tag:', error)
				})
		},
		confirmDelete() {
			if (this.isSaving) return
			const count = this.entries.length
			const message = count > 0
				? t('nextdiary', 'This tag is used in {count} entries. Delete it and remove it from those entries? The entries themselves will not be deleted.', { count })
				: t('nextdiary', 'Delete this tag?')
			if (!confirm(message)) {
				return
			}
			this.isSaving = true
			this.renameError = ''
			axios.delete(generateUrl('apps/nextdiary/api/tag/' + this.tagId))
				.then(() => {
					this.isSaving = false
					this.$emit('entry-changed')
					this.goBack()
				})
				.catch(error => {
					this.isSaving = false
					this.renameError = t('nextdiary', 'Could not delete')
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error deleting tag:', error)
				})
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
.tag-entries-view {
	padding: 20px 50px;

	.tag-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 20px;

		h2 {
			font-size: 20px;
			font-weight: 700;
			margin: 0;
			color: var(--color-primary);
		}

		.rename-input {
			flex-grow: 1;
			min-width: 0;
			font-size: 20px;
			font-weight: 700;
			padding: 2px 8px;
			border: 1px solid var(--color-border);
			border-radius: 8px;
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			outline: none;

			&:focus {
				border-color: var(--color-primary);
			}
		}
	}

	.rename-error {
		margin: -12px 0 20px;
		color: var(--color-error, #e9322d);
		font-size: 14px;
	}

	.tag-loading {
		display: flex;
		justify-content: center;
		padding: 40px;
	}

	.tag-entries {
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

		.entry-tags {
			margin-top: 6px;
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}
	}
}

.tag-badge {
	display: inline-block;
	padding: 2px 8px;
	background-color: #a5d6a7;
	color: #1b5e20;
	border-radius: 12px;
	font-size: 0.85em;
}

@media (max-width: 768px) {
	.tag-entries-view {
		padding: 12px 10px 12px 44px;

		.tag-header h2 {
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

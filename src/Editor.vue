<template>
	<div id="nextdiary-editor">
		<div id="entry-title">
			<NcButton type="tertiary"
				:aria-label="t('nextdiary', 'Back to day')"
				@click="goBack">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>
			<span class="entry-title-text">
				<i v-if="isLoading" class="fa fa-spinner fa-spin" />
				{{ unSavedMarker }}{{ title }}
			</span>
			<NcDateTimePickerNative
				id="entry-date-picker"
				:value="entryDateTimeObj"
				:label="t('nextdiary', 'Change date and time')"
				:hide-label="true"
				type="datetime-local"
				class="entry-date-picker"
				@input="onDateTimeChange" />
		</div>
		<div class="entry-meta-panel">
			<MoodSelector v-if="settings.show_mood || settings.show_wellbeing"
				:value="ratings"
				:show-mood="settings.show_mood"
				:show-wellbeing="settings.show_wellbeing"
				@input="onRatingsChange" />
			<div class="chips-row">
				<TagPicker v-if="settings.show_tags" :value="tags" @input="onTagsChange" />
				<SymptomPicker v-if="settings.show_symptoms" :value="symptoms" @input="onSymptomsChange" />
				<MedicationPicker v-if="settings.show_medications" :value="medications" @input="onMedicationsChange" />
				<FileUploadZone :uploading="fileUploading" @upload="onFilesUpload" />
			</div>
			<FileGallery :files="files" @delete="onFileDelete" />
		</div>
		<VueSimplemde ref="markdownEditor"
			:model-value="content"
			:configs="configs"
			preview-class="markdown-body" />
		<div v-if="isLoading" id="overlay">
			<i class="fa fa-spinner fa-spin fa-10x" />
		</div>
	</div>
</template>
<script>
import VueSimplemde from 'vue-simplemde'
import { NcButton, NcDateTimePickerNative } from '@nextcloud/vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft'
import MoodSelector from './MoodSelector.vue'
import TagPicker from './TagPicker.vue'
import SymptomPicker from './SymptomPicker.vue'
import MedicationPicker from './MedicationPicker.vue'
import FileUploadZone from './FileUploadZone.vue'
import FileGallery from './FileGallery.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

export default {
	name: 'EntryEditor',
	components: { VueSimplemde, NcButton, NcDateTimePickerNative, ArrowLeft, MoodSelector, TagPicker, SymptomPicker, MedicationPicker, FileUploadZone, FileGallery },
	props: {
		id: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			status: null,
			unSavedChanges: false,
			content: '',
			entryDate: null,
			createdAt: null,
			ratings: {},
			tags: [],
			symptoms: [],
			medications: [],
			files: [],
			fileUploading: false,
			settings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
			},
			configs: {
				toolbar: ['bold', 'italic', 'strikethrough', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', '|', 'preview', '|', 'guide'],
				autoDownloadFontAwesome: false,
				placeholder: t('nextdiary', 'Write your entry here'),
				spellChecker: false,
				status: false,
				parsingConfig: {
					highlightFormatting: true,
				},
			},
		}
	},
	computed: {
		simplemde() {
			return this.$refs.markdownEditor.simplemde
		},
		title() {
			if (!this.entryDate) return ''
			const day = moment(this.entryDate)
			let title = day.format('dddd') + ' - ' + day.format('LL')
			if (this.createdAt) {
				title += ' ' + moment(this.createdAt).format('HH:mm')
			}
			return title
		},
		entryDateTimeObj() {
			if (!this.entryDate) return null
			const parts = this.entryDate.split('-')
			if (this.createdAt) {
				const time = moment(this.createdAt)
				return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]), time.hours(), time.minutes())
			}
			return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
		},
		unSavedMarker() {
			return this.unSavedChanges ? '*' : ''
		},
		isLoading() {
			return this.status === 'loading'
		},
	},
	watch: {
		id() {
			this.fetchEntry()
		},
	},
	created() {
		this.fetchEntry()
		this.fetchSettings()
	},
	mounted() {
		this.simplemde.codemirror.on('change', () => {
			if (this.status === 'loading' || this.status === 'loaded' || this.content === this.simplemde.value()) {
				if (this.status === 'loaded') {
					this.status = 'writing'
				}
				return
			}
			this.content = this.simplemde.value()
			this.unSavedChanges = true
			clearTimeout(this.timeout)
			const entryId = this.id
			const saveFunction = () => {
				if (this.id !== entryId) return
				const newContent = this.simplemde.value()
				axios.put(generateUrl('apps/nextdiary/api/entry/' + entryId), {
					content: newContent,
					ratings: this.ratings,
					tags: this.tags,
					symptoms: this.symptoms,
					medications: this.medications,
				})
					.then(() => {
						if (this.id === entryId) {
							this.unSavedChanges = false
						}
						this.$emit('entry-changed')
					})
					.catch(error => {
						// eslint-disable-next-line no-console
						console.error('[NextDiary] Error saving entry:', error)
					})
			}
			this.timeout = setTimeout(saveFunction, 500)
		})
	},
	methods: {
		fetchEntry() {
			clearTimeout(this.timeout)
			this.unSavedChanges = false
			this.status = 'loading'
			axios.get(generateUrl('apps/nextdiary/api/entry/' + this.id))
				.then(response => {
					const data = response.data
					this.content = data.entryContent || ''
					this.entryDate = data.entryDate
					this.createdAt = data.createdAt
					this.ratings = data.entryRatings || {}
					this.tags = (data.tags || []).map(t => t.name)
					this.symptoms = (data.symptoms || []).map(s => s.name)
					this.medications = (data.medications || []).map(m => m.name)
					this.files = data.files || []
					this.status = 'loaded'
					this.simplemde.value(this.content)
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error fetching entry:', error)
					this.status = 'error'
				})
		},
		onRatingsChange(val) {
			this.ratings = val
			this.triggerMetaSave()
		},
		onTagsChange(val) {
			this.tags = val
			this.triggerMetaSave()
		},
		onSymptomsChange(val) {
			this.symptoms = val
			this.triggerMetaSave()
		},
		onMedicationsChange(val) {
			this.medications = val
			this.triggerMetaSave()
		},
		async fetchSettings() {
			try {
				const response = await axios.get(generateUrl('/apps/nextdiary/api/settings'))
				if (response.data) {
					this.settings = { ...this.settings, ...response.data }
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error fetching settings:', error)
			}
		},
		triggerMetaSave() {
			this.unSavedChanges = true
			clearTimeout(this.metaTimeout)
			const entryId = this.id
			this.metaTimeout = setTimeout(() => {
				if (this.id !== entryId) return
				axios.put(generateUrl('apps/nextdiary/api/entry/' + entryId), {
					content: this.simplemde.value(),
					ratings: this.ratings,
					tags: this.tags,
					symptoms: this.symptoms,
					medications: this.medications,
				})
					.then(() => {
						if (this.id === entryId) {
							this.unSavedChanges = false
						}
						this.$emit('entry-changed')
					})
					.catch(error => {
						// eslint-disable-next-line no-console
						console.error('[NextDiary] Error saving meta:', error)
					})
			}, 500)
		},
		async onFilesUpload(fileList) {
			this.fileUploading = true
			for (const file of fileList) {
				const formData = new FormData()
				formData.append('file', file)
				try {
					const response = await axios.post(
						generateUrl('/apps/nextdiary/api/entry/{entryId}/files', { entryId: this.id }),
						formData,
						{ headers: { 'Content-Type': 'multipart/form-data' } }
					)
					this.files.push(response.data)
				} catch (error) {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] File upload error:', error)
				}
			}
			this.fileUploading = false
		},
		async onFileDelete(file) {
			try {
				await axios.delete(
					generateUrl('/apps/nextdiary/api/entry/{entryId}/files/{fileId}', { entryId: this.id, fileId: file.id })
				)
				this.files = this.files.filter(f => f.id !== file.id)
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] File delete error:', error)
			}
		},
		onDateTimeChange(date) {
			if (!date || !(date instanceof Date)) return
			const yyyy = date.getFullYear().toString().padStart(4, '0')
			const mm = (date.getMonth() + 1).toString().padStart(2, '0')
			const dd = date.getDate().toString().padStart(2, '0')
			const newDate = `${yyyy}-${mm}-${dd}`
			const newLocalTime = moment(date).format('HH:mm')
			const oldLocalTime = this.createdAt ? moment(this.createdAt).format('HH:mm') : null
			if (newDate === this.entryDate && newLocalTime === oldLocalTime) return
			const dateChanged = newDate !== this.entryDate
			const entryId = this.id
			axios.put(generateUrl('apps/nextdiary/api/entry/' + entryId), {
				content: this.simplemde.value(),
				ratings: this.ratings,
				tags: this.tags,
				symptoms: this.symptoms,
				medications: this.medications,
				entryDate: newDate,
				entryDateTime: date.toISOString(),
			})
				.then(() => {
					this.entryDate = newDate
					this.createdAt = date.toISOString()
					this.unSavedChanges = false
					this.$emit('entry-changed')
					if (dateChanged) {
						this.$router.push({ name: 'day', params: { date: newDate } })
					}
				})
				.catch(error => {
					// eslint-disable-next-line no-console
					console.error('[NextDiary] Error changing date/time:', error)
				})
		},
		goBack() {
			if (this.entryDate) {
				this.$router.push({ name: 'day', params: { date: this.entryDate } })
			} else {
				this.$router.push({ name: 'day', params: { date: moment().format('YYYY-MM-DD') } })
			}
		},
	},
}
</script>

<style lang="scss">
@import '~@fortawesome/fontawesome-free/css/all.min.css';
@import '~simplemde/dist/simplemde.min.css';
@import '~github-markdown-css';

#nextdiary-editor {
	position: relative;
	height: 100%;
	width: 100%;

	#entry-title {
		display: flex;
		align-items: center;
		gap: 8px;
		font-weight: 700;
		font-size: 18px;
		padding-left: 52px;
		padding-top: 16px;

		@media (max-width: 768px) {
			padding-left: 44px;
			padding-top: 10px;
			font-size: 15px;
			gap: 4px;
		}

		.entry-title-text {
			flex: 1;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.entry-date-picker {
			flex-shrink: 0;
			margin-right: 8px;

			label { display: none; }

			.native-datetime-picker--input {
				width: 36px;
				height: 36px;
				padding: 0;
				border: none;
				background: transparent;
				cursor: pointer;
				color: transparent;
				position: relative;

				&::-webkit-calendar-picker-indicator {
					position: absolute;
					top: 0;
					left: 0;
					width: 100%;
					height: 100%;
					cursor: pointer;
					opacity: 0.6;
				}

				&:hover::-webkit-calendar-picker-indicator {
					opacity: 1;
				}
			}

			@media (max-width: 768px) {
				margin-right: 4px;

				.native-datetime-picker--input {
					width: 32px;
					height: 32px;
				}
			}
		}
	}

	.entry-meta-panel {
		padding: 0 52px 4px;
		border-bottom: 1px solid var(--color-border);
		margin-bottom: 4px;

		@media (max-width: 768px) {
			padding: 0 12px 4px 44px;
		}
	}

	.chips-row {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 6px;
		padding: 4px 0;
	}

	.vue-simplemde {
		padding-left: 32px;
		@media (max-width: 768px) {
			padding-left: 0;
			padding-right: 0;
		}

		.CodeMirror {
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			border: none;
		}

		.CodeMirror, .CodeMirror-scroll {
			padding-bottom: 50px;
		}

		.CodeMirror-cursor {
			border-color: var(--color-main-text);
		}

		.CodeMirror-code {
			width: unset !important;
			border: none !important;
		}

		.editor-toolbar {
			border: none;

			@media (max-width: 768px) {
				padding: 4px 8px;

				a {
					width: 28px !important;
					height: 28px !important;
				}

				i.separator {
					margin: 0 3px !important;
				}
			}

			a {
				color: var(--color-main-text) !important;

				&.active, &:hover {
					background-color: var(--color-background-hover) !important;
				}
			}

			&.disabled-for-preview {
				a:not(.no-disable) {
					background-color: var(--color-background-darker) !important;
					color: var(--color-text-lighter) !important;
				}
			}
		}

		.editor-preview {
			background-color: var(--color-main-background);
			color: var(--color-main-text);
		}

		.CodeMirror .cm-formatting {
			font-size: 1px !important;
			letter-spacing: -1ch;
			color: transparent;
			font-family: monospace;
		}

		.CodeMirror .CodeMirror-activeline .cm-formatting {
			font-size: inherit !important;
			letter-spacing: inherit;
			color: inherit;
			font-family: inherit;
			opacity: 0.4;
		}
	}

	#overlay {
		display: flex;
		justify-content: center;
		align-items: center;
		z-index: 99;
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: hsl(0, 0%, 0%, 0.5);
	}
}
</style>

<template>
	<div id="nextdiary-editor">
		<div id="entry-title">
			<NcButton variant="tertiary"
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
			<div class="entry-date-picker-wrap">
				<CalendarEdit :size="20" />
				<NcDateTimePickerNative id="entry-date-picker"
					:model-value="entryDateTimeObj"
					:label="t('nextdiary', 'Change date and time')"
					:hide-label="true"
					type="datetime-local"
					class="entry-date-picker"
					@update:model-value="onDateTimeChange" />
			</div>
			<NcButton variant="tertiary"
				:aria-label="t('nextdiary', 'Export')"
				@click="showExportDialog = true">
				<template #icon>
					<Download :size="20" />
				</template>
			</NcButton>
		</div>
		<ExportDialog v-if="showExportDialog"
			:entry-id="id"
			:current-date="entryDate"
			@close="showExportDialog = false" />
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
		<div class="nextdiary-markdown-editor">
			<textarea ref="markdownEditor" />
		</div>
		<div v-if="isLoading" id="overlay">
			<i class="fa fa-spinner fa-spin fa-10x" />
		</div>
	</div>
</template>
<script>
import CodeMirror from 'codemirror'
import DOMPurify from 'dompurify'
import EasyMDE from 'easymde'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft'
import CalendarEdit from 'vue-material-design-icons/CalendarEdit'
import Download from 'vue-material-design-icons/Download'
import ExportDialog from './ExportDialog.vue'
import MoodSelector from './MoodSelector.vue'
import TagPicker from './TagPicker.vue'
import SymptomPicker from './SymptomPicker.vue'
import MedicationPicker from './MedicationPicker.vue'
import FileUploadZone from './FileUploadZone.vue'
import FileGallery from './FileGallery.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

const TOOLBAR = ['bold', 'italic', 'strikethrough', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', '|', 'preview', '|', 'guide']

/**
 * Sanitize the Markdown preview HTML (the preview is inserted with innerHTML).
 * `target` is kept for the links EasyMDE opens in a new tab.
 *
 * @param {string} html rendered Markdown
 * @return {string}
 */
function sanitizePreview(html) {
	return DOMPurify.sanitize(html, { ADD_ATTR: ['target'] })
}

/**
 * Let the mobile keyboard (iOS / Android) do its usual work in the editor:
 * double-space -> ". ", autocorrect, auto-capitalization, spell checking.
 *
 * CodeMirror 5 turns all of this off on its input field (`disableBrowserMagic`),
 * and EasyMDE only forwards `spellcheck`. On mobile CodeMirror edits through a
 * contenteditable element, where the keyboard features work reliably, so they
 * are enabled there only; the desktop (hidden textarea input) stays unchanged.
 *
 * @param {object} cm CodeMirror instance
 */
function enableMobileKeyboardFeatures(cm) {
	if (cm.getOption('inputStyle') !== 'contenteditable') {
		return
	}
	// Keep the options in sync, so a re-created input field gets them too
	if (!cm.getOption('autocorrect')) cm.setOption('autocorrect', true)
	if (!cm.getOption('autocapitalize')) cm.setOption('autocapitalize', true)
	if (!cm.getOption('spellcheck')) cm.setOption('spellcheck', true)
	const field = cm.getInputField()
	field.setAttribute('autocorrect', 'on')
	field.setAttribute('autocapitalize', 'sentences')
	field.setAttribute('spellcheck', 'true')
}

/**
 * @param {HTMLTextAreaElement} element textarea to turn into the editor
 * @return {EasyMDE}
 */
function createMarkdownEditor(element) {
	// CodeMirror's own default: "contenteditable" on mobile devices (incl. iPadOS)
	const inputStyle = CodeMirror.defaults.inputStyle
	const editor = new EasyMDE({
		element,
		toolbar: TOOLBAR,
		autoDownloadFontAwesome: false,
		placeholder: t('nextdiary', 'Write your entry here'),
		spellChecker: false,
		nativeSpellcheck: inputStyle === 'contenteditable',
		inputStyle,
		styleSelectedText: false,
		status: false,
		previewClass: ['editor-preview', 'markdown-body'],
		parsingConfig: {
			highlightFormatting: true,
		},
		renderingConfig: {
			sanitizerFunction: sanitizePreview,
		},
		previewRender(plainText) {
			// Sanitize the final HTML once more, after EasyMDE's own post-processing
			return sanitizePreview(this.parent.markdown(plainText))
		},
	})
	const cm = editor.codemirror
	enableMobileKeyboardFeatures(cm)
	cm.on('focus', enableMobileKeyboardFeatures)
	return editor
}

export default {
	// eslint-disable-next-line vue/match-component-file-name
	name: 'EntryEditor',
	components: { NcButton, NcDateTimePickerNative, ArrowLeft, CalendarEdit, Download, ExportDialog, MoodSelector, TagPicker, SymptomPicker, MedicationPicker, FileUploadZone, FileGallery },
	props: {
		id: {
			type: String,
			required: true,
		},
	},
	emits: ['entry-changed'],
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
			showExportDialog: false,
			settings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
			},
		}
	},
	computed: {
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
		// Not reactive on purpose: the editor instance holds DOM / CodeMirror state.
		this.easymde = createMarkdownEditor(this.$refs.markdownEditor)
		this.easymde.codemirror.on('change', () => {
			if (this.status === 'loading' || this.status === 'loaded' || this.content === this.easymde.value()) {
				if (this.status === 'loaded') {
					this.status = 'writing'
				}
				return
			}
			this.content = this.easymde.value()
			this.unSavedChanges = true
			clearTimeout(this.timeout)
			const entryId = this.id
			const saveFunction = () => {
				if (this.id !== entryId) return
				const newContent = this.editorContent()
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
		if (this.status === 'loaded') {
			// The entry arrived before the editor existed
			this.easymde.value(this.content)
		}
	},
	beforeUnmount() {
		// Pending saves are not cancelled (same as before): they fall back to
		// `content`, which always holds the latest editor text.
		if (this.easymde) {
			this.easymde.cleanup()
			this.easymde.toTextArea()
			this.easymde = null
		}
	},
	methods: {
		editorContent() {
			return this.easymde ? this.easymde.value() : this.content
		},
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
					if (this.easymde) {
						this.easymde.value(this.content)
					}
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
					content: this.editorContent(),
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
				content: this.editorContent(),
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
@import '~easymde/dist/easymde.min.css';
@import '~github-markdown-css/github-markdown.css';

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

		.entry-date-picker-wrap {
			flex-shrink: 0;
			position: relative;
			width: 36px;
			height: 36px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			border-radius: 50%;
			margin-right: 8px;

			&:hover {
				background-color: var(--color-background-hover);
			}

			> .material-design-icon {
				color: var(--color-main-text);
				opacity: 0.6;
				pointer-events: none;
			}

			.entry-date-picker {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				margin: 0;

				label { display: none; }

				.input-field__main-wrapper {
					height: 100%;
				}

				input {
					width: 100%;
					height: 100%;
					padding: 0;
					border: none;
					background: transparent;
					cursor: pointer;
					color: transparent;
					position: absolute;
					top: 0;
					left: 0;
					opacity: 0;
				}
			}

			@media (max-width: 768px) {
				width: 32px;
				height: 32px;
				margin-right: 4px;
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

	.nextdiary-markdown-editor {
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

		// Heading sizes as in the previous editor (SimpleMDE)
		.CodeMirror {
			.cm-header-1 { font-size: 200%; line-height: 200%; }
			.cm-header-2 { font-size: 160%; line-height: 160%; }
			.cm-header-3 { font-size: 125%; line-height: 125%; }
			.cm-header-4 { font-size: 110%; line-height: 110%; }
			.cm-header-5, .cm-header-6 { font-size: inherit; line-height: inherit; }

			.cm-header-1, .cm-header-2, .cm-header-3, .cm-header-4, .cm-header-5, .cm-header-6 {
				margin-bottom: 0;
			}
		}

		.editor-toolbar {
			border: none;

			@media (max-width: 768px) {
				padding: 4px 8px;

				a, button {
					width: 28px !important;
					height: 28px !important;
					min-width: 28px !important;
				}

				i.separator {
					margin: 0 3px !important;
				}
			}

			// EasyMDE renders <button>s, which the server styles globally: reset to the toolbar look
			button {
				min-height: 0;
				min-width: 30px;
				width: 30px;
				height: 30px;
				margin: 0;
				padding: 0;
				border: 1px solid transparent;
				border-radius: 3px;
				background: transparent;
				font-weight: normal;
				font-size: inherit;
				box-shadow: none;
			}

			a, button {
				color: var(--color-main-text) !important;

				&.active, &:hover {
					background-color: var(--color-background-hover) !important;
				}
			}

			&.disabled-for-preview {
				a:not(.no-disable),
				button:not(.no-disable) {
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

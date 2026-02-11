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
			<span>
				<i v-if="isLoading" class="fa fa-spinner fa-spin" />
				{{ unSavedMarker }}{{ title }}
			</span>
		</div>
		<div class="entry-meta-panel">
			<MoodSelector :value="ratings" @input="onRatingsChange" />
			<div class="chips-row">
				<TagPicker :value="tags" @input="onTagsChange" />
				<SymptomPicker :value="symptoms" @input="onSymptomsChange" />
			</div>
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
import { NcButton } from '@nextcloud/vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft'
import MoodSelector from './MoodSelector.vue'
import TagPicker from './TagPicker.vue'
import SymptomPicker from './SymptomPicker.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

export default {
	name: 'EntryEditor',
	components: { VueSimplemde, NcButton, ArrowLeft, MoodSelector, TagPicker, SymptomPicker },
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
			padding-left: 12px;
			padding-top: 10px;
			font-size: 15px;
			gap: 4px;
		}
	}

	.entry-meta-panel {
		padding: 0 52px 4px;
		border-bottom: 1px solid var(--color-border);
		margin-bottom: 4px;

		@media (max-width: 768px) {
			padding: 0 12px 4px;
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

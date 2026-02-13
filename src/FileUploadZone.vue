<template>
	<div class="file-upload-zone"
		:class="{ 'is-dragging': isDragging }"
		@dragover.prevent="isDragging = true"
		@dragleave.prevent="isDragging = false"
		@drop.prevent="onDrop">
		<input ref="fileInput"
			type="file"
			multiple
			:accept="accept"
			class="file-input-hidden"
			@change="onFileSelect">
		<NcButton type="tertiary"
			:aria-label="t('nextdiary', 'Attach file')"
			@click="$refs.fileInput.click()">
			<template #icon>
				<Paperclip :size="18" />
			</template>
		</NcButton>
		<span v-if="uploading" class="upload-status">
			<i class="fa fa-spinner fa-spin" /> {{ t('nextdiary', 'Uploading...') }}
		</span>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Paperclip from 'vue-material-design-icons/Paperclip'

export default {
	name: 'FileUploadZone',
	components: { NcButton, Paperclip },
	props: {
		uploading: {
			type: Boolean,
			default: false,
		},
		accept: {
			type: String,
			default: 'image/*,.pdf,.doc,.docx,.txt,.md',
		},
	},
	data() {
		return {
			isDragging: false,
		}
	},
	methods: {
		onDrop(event) {
			this.isDragging = false
			const files = event.dataTransfer?.files
			if (files && files.length > 0) {
				this.$emit('upload', Array.from(files))
			}
		},
		onFileSelect(event) {
			const files = event.target.files
			if (files && files.length > 0) {
				this.$emit('upload', Array.from(files))
			}
			event.target.value = ''
		},
	},
}
</script>

<style lang="scss" scoped>
.file-upload-zone {
	display: flex;
	align-items: center;
	gap: 6px;

	&.is-dragging {
		background: var(--color-primary-element-light);
		border-radius: 8px;
	}

	.file-input-hidden {
		display: none;
	}

	.upload-status {
		font-size: 12px;
		color: var(--color-text-lighter);
	}
}
</style>

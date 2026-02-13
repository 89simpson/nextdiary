<template>
	<div v-if="files.length > 0" class="file-gallery">
		<div v-for="file in files" :key="file.id" class="file-item">
			<div v-if="file.isImage" class="file-preview">
				<img :src="previewUrl(file.id)" :alt="file.originalName" loading="lazy" @click="openFile(file)">
			</div>
			<div v-else class="file-icon" @click="openFile(file)">
				<Paperclip :size="24" />
			</div>
			<div class="file-info">
				<span class="file-name" :title="file.originalName">{{ file.originalName }}</span>
				<span class="file-size">{{ formatSize(file.sizeBytes) }}</span>
			</div>
			<NcButton type="tertiary"
				class="file-delete"
				:aria-label="t('nextdiary', 'Delete file')"
				@click="$emit('delete', file)">
				<template #icon>
					<Close :size="16" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import Close from 'vue-material-design-icons/Close'
import Paperclip from 'vue-material-design-icons/Paperclip'

export default {
	name: 'FileGallery',
	components: { NcButton, Close, Paperclip },
	props: {
		files: {
			type: Array,
			default: () => [],
		},
	},
	methods: {
		previewUrl(fileId) {
			return generateUrl('/apps/nextdiary/api/files/{fileId}/download', { fileId })
		},
		openFile(file) {
			window.open(this.previewUrl(file.id), '_blank')
		},
		formatSize(bytes) {
			if (bytes < 1024) return bytes + ' B'
			if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
			return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
		},
	},
}
</script>

<style lang="scss" scoped>
.file-gallery {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	padding: 8px 0;
}

.file-item {
	display: flex;
	align-items: center;
	gap: 6px;
	background: var(--color-background-dark);
	border-radius: 8px;
	padding: 4px 6px;
	max-width: 220px;
	min-width: 0;

	.file-preview {
		flex-shrink: 0;
		width: 40px;
		height: 40px;
		border-radius: 4px;
		overflow: hidden;
		cursor: pointer;

		img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
	}

	.file-icon {
		flex-shrink: 0;
		width: 40px;
		height: 40px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--color-background-darker);
		border-radius: 4px;
		cursor: pointer;
		color: var(--color-text-lighter);
	}

	.file-info {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;

		.file-name {
			font-size: 12px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.file-size {
			font-size: 10px;
			color: var(--color-text-lighter);
		}
	}

	.file-delete {
		flex-shrink: 0;
		min-width: 28px !important;
		min-height: 28px !important;
		width: 28px !important;
		height: 28px !important;
	}
}

@media (max-width: 768px) {
	.file-item {
		max-width: 100%;
	}
}
</style>

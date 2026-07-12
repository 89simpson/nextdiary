<template>
	<div class="tag-cloud">
		<div v-if="tags.length === 0" class="tag-cloud-empty">
			{{ t('nextdiary', 'No tags yet') }}
		</div>
		<span v-for="tag in tags"
			:key="tag.id"
			class="tag-item"
			:class="{ active: activeTagId === tag.id }"
			:style="{ fontSize: calculateFontSize(tag.count) }"
			@click="$emit('select-tag', tag.id)">
			{{ tag.name }}
			<small>({{ tag.count }})</small>
		</span>
	</div>
</template>

<script>
export default {
	name: 'TagCloud',
	props: {
		tags: {
			type: Array,
			default: () => [],
		},
		activeTagId: {
			type: Number,
			default: null,
		},
	},
	methods: {
		calculateFontSize(count) {
			const min = 0.85
			const max = 1.4
			// Empty references (count 0) still render, at the base minimum size.
			if (!count) return min + 'em'
			if (!this.tags.length) return min + 'em'
			const maxCount = Math.max(...this.tags.map(t => t.count))
			const minCount = Math.min(...this.tags.map(t => t.count))
			// Avoid division by zero when every count is equal.
			if (maxCount === minCount) return '1em'
			const ratio = (count - minCount) / (maxCount - minCount)
			return (min + ratio * (max - min)).toFixed(2) + 'em'
		},
	},
}
</script>

<style lang="scss" scoped>
.tag-cloud {
	padding: 12px;

	.tag-cloud-empty {
		color: var(--color-text-lighter);
		font-style: italic;
		padding: 8px 0;
	}

	.tag-item {
		display: inline-block;
		margin: 3px;
		padding: 4px 10px;
		background-color: var(--color-background-hover);
		border-radius: 16px;
		cursor: pointer;
		transition: all 0.2s;

		&:hover {
			background-color: #a5d6a7;
			color: #1b5e20;
		}

		&.active {
			background-color: #a5d6a7;
			color: #1b5e20;
			font-weight: 700;
		}

		small {
			font-size: 0.8em;
			opacity: 0.7;
			margin-left: 2px;
		}
	}
}
</style>

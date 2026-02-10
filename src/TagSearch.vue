<template>
	<div class="tag-search">
		<input
			:value="value"
			type="text"
			class="tag-search-input"
			:placeholder="t('nextdiary', 'Search tags...')"
			@input="$emit('input', $event.target.value)">
	</div>
</template>

<script>
export default {
	name: 'TagSearch',
	props: {
		value: {
			type: String,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
.tag-search {
	padding: 12px 12px 0;

	.tag-search-input {
		width: 100%;
		padding: 8px 12px;
		border: 1px solid var(--color-border);
		border-radius: 20px;
		background-color: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 14px;
		outline: none;

		&:focus {
			border-color: var(--color-primary);
		}
	}
}
</style>

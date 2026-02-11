<template>
	<div class="tag-picker">
		<div class="tag-input-row">
			<input ref="input"
				v-model="inputValue"
				type="text"
				class="tag-input"
				:placeholder="t('nextdiary', 'Add tag...')"
				@keydown.enter.prevent="addTag"
				@input="onInput">
			<ul v-if="suggestions.length > 0" class="tag-suggestions">
				<li v-for="s in suggestions"
					:key="s.id"
					@mousedown.prevent="selectSuggestion(s)">
					{{ s.name }}
				</li>
			</ul>
		</div>
		<div v-if="tags.length > 0" class="tag-chips">
			<span v-for="(tag, idx) in tags"
				:key="idx"
				class="tag-chip">
				#{{ tag }}
				<span class="remove" @click="removeTag(idx)">&times;</span>
			</span>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'TagPicker',
	props: {
		value: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			inputValue: '',
			allTags: [],
			suggestions: [],
		}
	},
	computed: {
		tags() {
			return this.value || []
		},
	},
	mounted() {
		this.fetchAllTags()
	},
	methods: {
		fetchAllTags() {
			axios.get(generateUrl('apps/nextdiary/api/tags'))
				.then(response => {
					this.allTags = (response.data || []).map(t => t.name)
				})
				.catch(() => {})
		},
		onInput() {
			const q = this.inputValue.trim().toLowerCase()
			if (!q) {
				this.suggestions = []
				return
			}
			this.suggestions = this.allTags
				.filter(name => name.toLowerCase().includes(q) && !this.tags.includes(name))
				.slice(0, 5)
				.map((name, id) => ({ id, name }))
		},
		addTag() {
			const name = this.inputValue.trim().toLowerCase()
			if (!name) return
			if (!this.tags.includes(name)) {
				this.$emit('input', [...this.tags, name])
			}
			this.inputValue = ''
			this.suggestions = []
		},
		selectSuggestion(s) {
			if (!this.tags.includes(s.name)) {
				this.$emit('input', [...this.tags, s.name])
			}
			this.inputValue = ''
			this.suggestions = []
		},
		removeTag(idx) {
			const updated = [...this.tags]
			updated.splice(idx, 1)
			this.$emit('input', updated)
		},
	},
}
</script>

<style lang="scss" scoped>
.tag-picker {
	padding: 4px 0;

	.tag-input-row {
		position: relative;
	}

	.tag-input {
		width: 200px;
		padding: 4px 8px;
		border: 1px solid var(--color-border);
		border-radius: 4px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 13px;

		&:focus {
			border-color: var(--color-primary);
			outline: none;
		}
	}

	.tag-suggestions {
		position: absolute;
		top: 100%;
		left: 0;
		z-index: 100;
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: 4px;
		list-style: none;
		margin: 2px 0 0;
		padding: 0;
		width: 200px;
		max-height: 150px;
		overflow-y: auto;

		li {
			padding: 6px 10px;
			cursor: pointer;
			font-size: 13px;

			&:hover {
				background: var(--color-background-hover);
			}
		}
	}

	.tag-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		margin-top: 6px;
	}

	.tag-chip {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		padding: 2px 8px;
		background-color: #a5d6a7;
		color: #1b5e20;
		border-radius: 12px;
		font-size: 0.85em;

		.remove {
			cursor: pointer;
			font-weight: 700;
			opacity: 0.7;

			&:hover {
				opacity: 1;
			}
		}
	}
}
</style>

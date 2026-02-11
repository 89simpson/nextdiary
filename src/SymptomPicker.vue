<template>
	<div class="chip-picker symptom-picker">
		<div class="picker-header">
			<span class="picker-label">{{ t('nextdiary', 'Symptoms') }}</span>
			<span v-for="name in selected"
				:key="'sel-' + name"
				class="chip selected"
				@click="toggle(name)">
				{{ name }} &times;
			</span>
			<button class="picker-toggle" @click="expanded = !expanded">
				{{ expanded ? '−' : '+' }}
			</button>
		</div>
		<div v-if="expanded" class="picker-cloud">
			<span v-for="name in cloudItems"
				:key="'cloud-' + name"
				class="chip"
				:class="{ selected: isSelected(name) }"
				@click="toggle(name)">
				{{ name }}
			</span>
			<span v-if="hasMore" class="chip more" @click="showAll = !showAll">
				{{ showAll ? t('nextdiary', 'less') : t('nextdiary', 'more...') }}
			</span>
			<div class="picker-input-row">
				<input v-model="inputValue"
					type="text"
					class="picker-input"
					:placeholder="t('nextdiary', 'New symptom...')"
					@keydown.enter.prevent="addNew"
					@input="onInput">
				<ul v-if="suggestions.length > 0" class="picker-suggestions">
					<li v-for="s in suggestions"
						:key="s"
						@mousedown.prevent="toggle(s)">
						{{ s }}
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const MAX_VISIBLE = 10

export default {
	name: 'SymptomPicker',
	props: {
		value: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			expanded: false,
			showAll: false,
			inputValue: '',
			allItems: [],
			suggestions: [],
		}
	},
	computed: {
		selected() {
			return this.value || []
		},
		cloudItems() {
			const items = this.allItems.filter(n => !this.isSelected(n))
			if (this.showAll) return items
			return items.slice(0, MAX_VISIBLE)
		},
		hasMore() {
			return this.allItems.filter(n => !this.isSelected(n)).length > MAX_VISIBLE
		},
	},
	mounted() {
		this.fetchItems()
	},
	methods: {
		fetchItems() {
			axios.get(generateUrl('apps/nextdiary/api/symptoms'))
				.then(response => {
					this.allItems = (response.data || [])
						.sort((a, b) => b.count - a.count)
						.map(s => s.name)
				})
				.catch(() => {})
		},
		isSelected(name) {
			return this.selected.includes(name)
		},
		toggle(name) {
			if (this.isSelected(name)) {
				this.$emit('input', this.selected.filter(n => n !== name))
			} else {
				this.$emit('input', [...this.selected, name])
			}
		},
		addNew() {
			const name = this.inputValue.trim()
			if (!name) return
			if (!this.isSelected(name)) {
				this.$emit('input', [...this.selected, name])
			}
			this.inputValue = ''
			this.suggestions = []
		},
		onInput() {
			const q = this.inputValue.trim().toLowerCase()
			if (!q) {
				this.suggestions = []
				return
			}
			this.suggestions = this.allItems
				.filter(name => name.toLowerCase().includes(q) && !this.isSelected(name))
				.slice(0, 5)
		},
	},
}
</script>

<style lang="scss" scoped>
.chip-picker {
	display: contents;

	.picker-header {
		display: flex;
		align-items: center;
		gap: 6px;
		flex-wrap: wrap;
		order: 0;
	}

	.picker-label {
		font-size: 13px;
		font-weight: 600;
		color: var(--color-text-lighter);
		white-space: nowrap;

		@media (max-width: 768px) {
			font-size: 11px;
		}
	}

	.picker-toggle {
		background: none;
		border: 1px solid var(--color-border);
		border-radius: 50%;
		width: 22px;
		height: 22px;
		min-width: 22px;
		min-height: 22px;
		flex-shrink: 0;
		box-sizing: border-box;
		cursor: pointer;
		color: var(--color-text-lighter);
		font-size: 14px;
		line-height: 1;
		padding: 0;
		display: flex;
		align-items: center;
		justify-content: center;

		@media (max-width: 768px) {
			width: 28px;
			height: 28px;
			min-width: 28px;
			min-height: 28px;
			font-size: 16px;
		}

		&:hover {
			background: var(--color-background-hover);
			color: var(--color-main-text);
		}
	}

	.picker-cloud {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		margin-top: 6px;
		align-items: center;
		order: 1;
		width: 100%;
		flex-basis: 100%;
	}

	.chip {
		display: inline-flex;
		align-items: center;
		padding: 3px 10px;
		border-radius: 14px;
		font-size: 0.85em;
		cursor: pointer;
		transition: all 0.15s;
		user-select: none;
	}

	.chip.more {
		font-style: italic;
		opacity: 0.6;

		&:hover {
			opacity: 1;
		}
	}

	.picker-input-row {
		position: relative;
		margin-top: 4px;
		width: 100%;
	}

	.picker-input {
		width: 180px;
		padding: 3px 8px;
		border: 1px solid var(--color-border);
		border-radius: 4px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 12px;

		@media (max-width: 768px) {
			width: 100%;
			padding: 6px 10px;
			font-size: 14px;
			box-sizing: border-box;
		}

		&:focus {
			border-color: var(--color-primary);
			outline: none;
		}
	}

	.picker-suggestions {
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
		width: 180px;
		max-height: 120px;
		overflow-y: auto;

		@media (max-width: 768px) {
			width: 100%;
		}

		li {
			padding: 5px 10px;
			cursor: pointer;
			font-size: 12px;

			@media (max-width: 768px) {
				padding: 8px 12px;
				font-size: 14px;
			}

			&:hover {
				background: var(--color-background-hover);
			}
		}
	}
}

.symptom-picker {
	.chip {
		background-color: var(--color-background-dark);
		color: var(--color-text-lighter);
		border: 1px solid var(--color-border);
		opacity: 0.7;

		&:hover {
			opacity: 1;
			border-color: #ef9a9a;
		}

		&.selected {
			background-color: #ef9a9a;
			color: #b71c1c;
			border-color: #ef9a9a;
			opacity: 1;
		}
	}
}
</style>

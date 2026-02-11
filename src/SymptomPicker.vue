<template>
	<div class="symptom-picker">
		<div class="symptom-input-row">
			<input ref="input"
				v-model="inputValue"
				type="text"
				class="symptom-input"
				:placeholder="t('nextdiary', 'Add symptom...')"
				@keydown.enter.prevent="addSymptom"
				@input="onInput">
			<ul v-if="suggestions.length > 0" class="symptom-suggestions">
				<li v-for="s in suggestions"
					:key="s.id"
					@mousedown.prevent="selectSuggestion(s)">
					{{ s.name }}
				</li>
			</ul>
		</div>
		<div v-if="symptoms.length > 0" class="symptom-chips">
			<span v-for="(symptom, idx) in symptoms"
				:key="idx"
				class="symptom-chip">
				{{ symptom }}
				<span class="remove" @click="removeSymptom(idx)">&times;</span>
			</span>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

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
			inputValue: '',
			allSymptoms: [],
			suggestions: [],
		}
	},
	computed: {
		symptoms() {
			return this.value || []
		},
	},
	mounted() {
		this.fetchAllSymptoms()
	},
	methods: {
		fetchAllSymptoms() {
			axios.get(generateUrl('apps/nextdiary/api/symptoms'))
				.then(response => {
					this.allSymptoms = (response.data || []).map(s => s.name)
				})
				.catch(() => {})
		},
		onInput() {
			const q = this.inputValue.trim().toLowerCase()
			if (!q) {
				this.suggestions = []
				return
			}
			this.suggestions = this.allSymptoms
				.filter(name => name.toLowerCase().includes(q) && !this.symptoms.includes(name))
				.slice(0, 5)
				.map((name, id) => ({ id, name }))
		},
		addSymptom() {
			const name = this.inputValue.trim()
			if (!name) return
			if (!this.symptoms.includes(name)) {
				this.$emit('input', [...this.symptoms, name])
			}
			this.inputValue = ''
			this.suggestions = []
		},
		selectSuggestion(s) {
			if (!this.symptoms.includes(s.name)) {
				this.$emit('input', [...this.symptoms, s.name])
			}
			this.inputValue = ''
			this.suggestions = []
		},
		removeSymptom(idx) {
			const updated = [...this.symptoms]
			updated.splice(idx, 1)
			this.$emit('input', updated)
		},
	},
}
</script>

<style lang="scss" scoped>
.symptom-picker {
	padding: 4px 0;

	.symptom-input-row {
		position: relative;
	}

	.symptom-input {
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

	.symptom-suggestions {
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

	.symptom-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		margin-top: 6px;
	}

	.symptom-chip {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		padding: 2px 8px;
		background-color: var(--color-error);
		color: white;
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

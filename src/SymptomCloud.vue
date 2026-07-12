<template>
	<div class="symptom-cloud">
		<div v-if="symptoms.length === 0" class="symptom-cloud-empty">
			{{ t('nextdiary', 'No symptoms yet') }}
		</div>
		<span v-for="symptom in symptoms"
			:key="symptom.id"
			class="symptom-item"
			:class="{ active: activeSymptomId === symptom.id }"
			:style="{ fontSize: calculateFontSize(symptom.count) }"
			@click="$emit('select-symptom', symptom.id)">
			{{ symptom.name }}
			<small>({{ symptom.count }})</small>
		</span>
	</div>
</template>

<script>
export default {
	name: 'SymptomCloud',
	props: {
		symptoms: {
			type: Array,
			default: () => [],
		},
		activeSymptomId: {
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
			if (!this.symptoms.length) return min + 'em'
			const maxCount = Math.max(...this.symptoms.map(s => s.count))
			const minCount = Math.min(...this.symptoms.map(s => s.count))
			// Avoid division by zero when every count is equal.
			if (maxCount === minCount) return '1em'
			const ratio = (count - minCount) / (maxCount - minCount)
			return (min + ratio * (max - min)).toFixed(2) + 'em'
		},
	},
}
</script>

<style lang="scss" scoped>
.symptom-cloud {
	padding: 12px;

	.symptom-cloud-empty {
		color: var(--color-text-lighter);
		font-style: italic;
		padding: 8px 0;
	}

	.symptom-item {
		display: inline-block;
		margin: 3px;
		padding: 4px 10px;
		background-color: var(--color-background-hover);
		border-radius: 16px;
		cursor: pointer;
		transition: all 0.2s;

		&:hover {
			background-color: #ef9a9a;
			color: #b71c1c;
		}

		&.active {
			background-color: #ef9a9a;
			color: #b71c1c;
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

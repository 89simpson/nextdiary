<template>
	<div class="medication-cloud">
		<div v-if="medications.length === 0" class="medication-cloud-empty">
			{{ t('nextdiary', 'No medications yet') }}
		</div>
		<span v-for="medication in medications"
			:key="medication.id"
			class="medication-item"
			:class="{ active: activeMedicationId === medication.id }"
			:style="{ fontSize: calculateFontSize(medication.count) }"
			@click="$emit('select-medication', medication.id)">
			{{ medication.name }}
			<small>({{ medication.count }})</small>
		</span>
	</div>
</template>

<script>
export default {
	name: 'MedicationCloud',
	props: {
		medications: {
			type: Array,
			default: () => [],
		},
		activeMedicationId: {
			type: Number,
			default: null,
		},
	},
	methods: {
		calculateFontSize(count) {
			const min = 0.85
			const max = 1.4
			if (!this.medications.length) return min + 'em'
			const maxCount = Math.max(...this.medications.map(s => s.count))
			const minCount = Math.min(...this.medications.map(s => s.count))
			if (maxCount === minCount) return '1em'
			const ratio = (count - minCount) / (maxCount - minCount)
			return (min + ratio * (max - min)).toFixed(2) + 'em'
		},
	},
}
</script>

<style lang="scss" scoped>
.medication-cloud {
	padding: 12px;

	.medication-cloud-empty {
		color: var(--color-text-lighter);
		font-style: italic;
		padding: 8px 0;
	}

	.medication-item {
		display: inline-block;
		margin: 3px;
		padding: 4px 10px;
		background-color: var(--color-background-hover);
		border-radius: 16px;
		cursor: pointer;
		transition: all 0.2s;

		&:hover {
			background-color: #90CAF9;
			color: #1565C0;
		}

		&.active {
			background-color: #90CAF9;
			color: #1565C0;
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

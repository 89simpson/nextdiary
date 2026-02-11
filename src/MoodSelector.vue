<template>
	<div class="mood-selector">
		<div class="mood-row">
			<label class="mood-label">{{ t('nextdiary', 'Mood') }}</label>
			<div class="mood-emojis">
				<span v-for="(emoji, idx) in moodEmojis"
					:key="'mood-' + idx"
					class="mood-emoji"
					:class="{ active: mood === idx + 1 }"
					:title="moodLabels[idx]"
					@click="setMood(idx + 1)">
					{{ emoji }}
				</span>
			</div>
		</div>
		<div class="mood-row">
			<label class="mood-label">{{ t('nextdiary', 'Wellbeing') }}</label>
			<div class="wellbeing-dots">
				<span v-for="n in 5"
					:key="'wb-' + n"
					class="wellbeing-dot"
					:class="{ active: wellbeing >= n }"
					@click="setWellbeing(n)">
					&#9679;
				</span>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'MoodSelector',
	props: {
		value: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
			moodEmojis: ['\uD83D\uDE1E', '\uD83D\uDE15', '\uD83D\uDE10', '\uD83D\uDE42', '\uD83D\uDE0A'],
			moodLabels: [
				t('nextdiary', 'Very bad'),
				t('nextdiary', 'Bad'),
				t('nextdiary', 'Neutral'),
				t('nextdiary', 'Good'),
				t('nextdiary', 'Great'),
			],
		}
	},
	computed: {
		mood() {
			return this.value?.mood || 0
		},
		wellbeing() {
			return this.value?.wellbeing || 0
		},
	},
	methods: {
		setMood(val) {
			const newVal = this.mood === val ? 0 : val
			this.$emit('input', { ...this.value, mood: newVal || undefined })
		},
		setWellbeing(val) {
			const newVal = this.wellbeing === val ? 0 : val
			this.$emit('input', { ...this.value, wellbeing: newVal || undefined })
		},
	},
}
</script>

<style lang="scss" scoped>
.mood-selector {
	display: flex;
	gap: 16px;
	align-items: center;
	padding: 8px 0;
	flex-wrap: wrap;

	@media (max-width: 768px) {
		gap: 8px;
		padding: 6px 0;
	}

	.mood-row {
		display: flex;
		align-items: center;
		gap: 8px;

		@media (max-width: 768px) {
			gap: 4px;
		}
	}

	.mood-label {
		font-size: 13px;
		font-weight: 600;
		color: var(--color-text-lighter);
		white-space: nowrap;

		@media (max-width: 768px) {
			font-size: 11px;
		}
	}

	.mood-emojis {
		display: flex;
		gap: 4px;

		@media (max-width: 768px) {
			gap: 2px;
		}
	}

	.mood-emoji {
		font-size: 22px;
		cursor: pointer;
		opacity: 0.4;
		transition: all 0.15s;
		padding: 2px;

		@media (max-width: 768px) {
			font-size: 20px;
			padding: 1px;
		}

		&:hover {
			opacity: 0.8;
			transform: scale(1.2);
		}

		&.active {
			opacity: 1;
			transform: scale(1.2);
		}
	}

	.wellbeing-dots {
		display: flex;
		gap: 4px;

		@media (max-width: 768px) {
			gap: 2px;
		}
	}

	.wellbeing-dot {
		font-size: 16px;
		cursor: pointer;
		color: var(--color-border-dark);
		transition: all 0.15s;

		@media (max-width: 768px) {
			font-size: 14px;
		}

		&:hover {
			color: var(--color-warning);
		}

		&.active {
			color: var(--color-warning);
		}
	}
}
</style>

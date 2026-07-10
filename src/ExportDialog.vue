<template>
	<div class="export-dialog-overlay" @click.self="$emit('close')">
		<div class="export-dialog">
			<h2>{{ t('nextdiary', 'Export entries') }}</h2>

			<div class="export-section">
				<label class="export-label">{{ t('nextdiary', 'What to export?') }}</label>
				<NcCheckboxRadioSwitch v-if="entryId"
					:checked.sync="scope"
					value="single"
					name="scope"
					type="radio">
					{{ t('nextdiary', 'Current entry') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="scope"
					value="day"
					name="scope"
					type="radio">
					{{ t('nextdiary', 'Single day') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="scope"
					value="range"
					name="scope"
					type="radio">
					{{ t('nextdiary', 'Date range') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="scope"
					value="all"
					name="scope"
					type="radio">
					{{ t('nextdiary', 'All entries') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div v-if="scope === 'day'" class="export-section">
				<label class="export-label">{{ t('nextdiary', 'Change date') }}</label>
				<NcDatetimePicker v-model="dayDate"
					type="date"
					class="export-datepicker" />
			</div>

			<div v-if="scope === 'range'" class="export-section">
				<div class="date-range-row">
					<div class="date-field">
						<label class="export-label">{{ t('nextdiary', 'Start date') }}</label>
						<NcDatetimePicker v-model="rangeStart"
							type="date"
							class="export-datepicker" />
					</div>
					<div class="date-field">
						<label class="export-label">{{ t('nextdiary', 'End date') }}</label>
						<NcDatetimePicker v-model="rangeEnd"
							type="date"
							class="export-datepicker" />
					</div>
				</div>
			</div>

			<div class="export-section">
				<label class="export-label">{{ t('nextdiary', 'Format') }}</label>
				<NcCheckboxRadioSwitch :checked.sync="format"
					value="markdown"
					name="format"
					type="radio">
					Markdown
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="format"
					value="pdf"
					name="format"
					type="radio">
					PDF
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="format"
					value="csv"
					name="format"
					type="radio">
					{{ t('nextdiary', 'CSV (for analysis)') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="export-actions">
				<NcButton type="tertiary" @click="$emit('close')">
					{{ t('nextdiary', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" @click="download">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('nextdiary', 'Download') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton, NcDatetimePicker } from '@nextcloud/vue'
import Download from 'vue-material-design-icons/Download'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

export default {
	name: 'ExportDialog',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcDatetimePicker,
		Download,
	},
	props: {
		entryId: {
			type: [Number, String],
			default: null,
		},
		currentDate: {
			type: String,
			default: () => moment().format('YYYY-MM-DD'),
		},
	},
	data() {
		return {
			scope: this.entryId ? 'single' : 'all',
			format: 'pdf',
			dayDate: this.currentDate ? new Date(this.currentDate + 'T00:00:00') : new Date(),
			rangeStart: this.currentDate ? new Date(this.currentDate + 'T00:00:00') : new Date(),
			rangeEnd: new Date(),
		}
	},
	methods: {
		formatDate(d) {
			return moment(d).format('YYYY-MM-DD')
		},
		download() {
			const baseUrl = generateUrl('apps/nextdiary')
			const paths = {
				markdown: '/export/markdown',
				pdf: '/export/pdf',
				csv: '/export/csv',
			}
			const exportPath = paths[this.format] || '/export/markdown'
			const params = new URLSearchParams()
			params.set('scope', this.scope)

			if (this.scope === 'single' && this.entryId) {
				params.set('entryId', this.entryId)
			} else if (this.scope === 'day') {
				params.set('date', this.formatDate(this.dayDate))
			} else if (this.scope === 'range') {
				params.set('startDate', this.formatDate(this.rangeStart))
				params.set('endDate', this.formatDate(this.rangeEnd))
			}

			window.location.href = baseUrl + exportPath + '?' + params.toString()
			this.$emit('close')
		},
	},
}
</script>

<style lang="scss" scoped>
.export-dialog-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 10000;
	display: flex;
	align-items: center;
	justify-content: center;
}

.export-dialog {
	background: var(--color-main-background);
	border-radius: 12px;
	padding: 24px;
	min-width: 360px;
	max-width: 480px;
	box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);

	h2 {
		font-size: 18px;
		font-weight: 700;
		margin: 0 0 16px;
	}
}

.export-section {
	margin-bottom: 16px;
}

.export-label {
	display: block;
	font-weight: 600;
	font-size: 14px;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
}

.date-range-row {
	display: flex;
	gap: 12px;

	.date-field {
		flex: 1;
	}
}

.export-datepicker {
	width: 100%;
}

.export-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 20px;
}
</style>

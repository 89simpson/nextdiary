<template>
	<div id="nextdiary-settings-page">
		<h2>{{ t('nextdiary', 'Diary settings') }}</h2>
		<div class="settings-section">
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_mood"
				@update:checked="updateSetting('show_mood', $event)">
				{{ t('nextdiary', 'Show mood') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_wellbeing"
				@update:checked="updateSetting('show_wellbeing', $event)">
				{{ t('nextdiary', 'Show wellbeing') }}
			</NcCheckboxRadioSwitch>
		</div>
		<h3>{{ t('nextdiary', 'Sidebar sections') }}</h3>
		<div class="settings-section">
			<div v-for="(sectionKey, index) in localSettings.sidebar_order"
				:key="sectionKey"
				class="settings-row">
				<NcCheckboxRadioSwitch :checked.sync="localSettings[showKey(sectionKey)]"
					@update:checked="updateSetting(showKey(sectionKey), $event)">
					{{ sectionLabel(sectionKey) }}
				</NcCheckboxRadioSwitch>
				<div class="order-buttons">
					<NcButton type="tertiary"
						:disabled="index === 0"
						:aria-label="t('nextdiary', 'Move up')"
						@click="moveSection(index, -1)">
						<template #icon>
							<ArrowUp :size="20" />
						</template>
					</NcButton>
					<NcButton type="tertiary"
						:disabled="index === localSettings.sidebar_order.length - 1"
						:aria-label="t('nextdiary', 'Move down')"
						@click="moveSection(index, 1)">
						<template #icon>
							<ArrowDown :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
		<h3>{{ t('nextdiary', 'Reference lists') }}</h3>
		<div class="settings-section">
			<NcCheckboxRadioSwitch :checked.sync="localSettings.auto_cleanup_unused"
				@update:checked="updateSetting('auto_cleanup_unused', $event)">
				{{ t('nextdiary', 'Automatically delete tags, symptoms and medications when no longer used in any entry') }}
			</NcCheckboxRadioSwitch>
			<p class="settings-hint">
				{{ t('nextdiary', 'When off, unused items stay in the clouds at minimal size so you can select and delete them manually.') }}
			</p>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp'
import ArrowDown from 'vue-material-design-icons/ArrowDown'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SettingsPage',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		ArrowUp,
		ArrowDown,
	},
	data() {
		return {
			localSettings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
				auto_cleanup_unused: true,
				sidebar_order: ['tags', 'symptoms', 'medications'],
			},
		}
	},
	mounted() {
		this.fetchSettings()
	},
	methods: {
		showKey(sectionKey) {
			return 'show_' + sectionKey
		},
		sectionLabel(key) {
			const labels = {
				tags: t('nextdiary', 'Tags'),
				symptoms: t('nextdiary', 'Symptoms'),
				medications: t('nextdiary', 'Medications'),
			}
			return labels[key] || key
		},
		async fetchSettings() {
			try {
				const response = await axios.get(generateUrl('/apps/nextdiary/api/settings'))
				if (response.data) {
					this.localSettings = { ...this.localSettings, ...response.data }
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error fetching settings:', error)
			}
		},
		async updateSetting(key, value) {
			try {
				await axios.put(generateUrl('/apps/nextdiary/api/settings'), {
					key,
					value,
				})
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error updating setting:', error)
			}
		},
		moveSection(index, direction) {
			const newIndex = index + direction
			const order = [...this.localSettings.sidebar_order]
			const temp = order[index]
			order[index] = order[newIndex]
			order[newIndex] = temp
			this.localSettings.sidebar_order = order
			this.updateSetting('sidebar_order', order)
		},
	},
}
</script>

<style lang="scss" scoped>
#nextdiary-settings-page {
	padding: 20px;

	h2 {
		font-size: 20px;
		font-weight: 700;
		margin-bottom: 20px;
	}

	h3 {
		font-size: 16px;
		font-weight: 600;
		margin-top: 24px;
		margin-bottom: 12px;
	}

	.settings-section {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		margin: 4px 0 0 0;
	}

	.settings-row {
		display: flex;
		align-items: center;
		gap: 8px;

		.order-buttons {
			display: flex;
			gap: 0;
			margin-left: auto;
		}
	}
}
</style>

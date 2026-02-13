<template>
	<div id="nextdiary-settings-page">
		<h2>{{ t('nextdiary', 'Diary settings') }}</h2>
		<div class="settings-list">
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_mood"
				@update:checked="updateSetting('show_mood', $event)">
				{{ t('nextdiary', 'Show mood') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_wellbeing"
				@update:checked="updateSetting('show_wellbeing', $event)">
				{{ t('nextdiary', 'Show wellbeing') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_tags"
				@update:checked="updateSetting('show_tags', $event)">
				{{ t('nextdiary', 'Show tags') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_symptoms"
				@update:checked="updateSetting('show_symptoms', $event)">
				{{ t('nextdiary', 'Show symptoms') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="localSettings.show_medications"
				@update:checked="updateSetting('show_medications', $event)">
				{{ t('nextdiary', 'Show medications') }}
			</NcCheckboxRadioSwitch>
		</div>
		<h3>{{ t('nextdiary', 'Sidebar order') }}</h3>
		<div class="order-list">
			<div v-for="(sectionKey, index) in localSettings.sidebar_order"
				:key="sectionKey"
				class="order-item">
				<span class="order-label">{{ sectionLabel(sectionKey) }}</span>
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
				sidebar_order: ['tags', 'symptoms', 'medications'],
			},
		}
	},
	mounted() {
		this.fetchSettings()
	},
	methods: {
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
		sectionLabel(key) {
			const labels = {
				tags: t('nextdiary', 'Tags'),
				symptoms: t('nextdiary', 'Symptoms'),
				medications: t('nextdiary', 'Medications'),
			}
			return labels[key] || key
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

	.settings-list {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	h3 {
		font-size: 16px;
		font-weight: 600;
		margin-top: 24px;
		margin-bottom: 12px;
	}

	.order-list {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.order-item {
		display: flex;
		align-items: center;
		gap: 8px;

		.order-label {
			flex-grow: 1;
			font-size: 14px;
		}
	}
}
</style>

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
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SettingsPage',
	components: {
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			localSettings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
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
}
</style>

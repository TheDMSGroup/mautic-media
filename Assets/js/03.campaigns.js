// Campaigns field.
Mautic.mediaCampaigns = function () {
    var $campaigns = mQuery('#media_campaign_settings:first:not(.campaigns-checked)');
    if ($campaigns.length) {
        $campaigns.addClass('campaigns-checked');
        // Retrieve the list of available campaigns via Ajax
        var campaigns = {},
            providerCampaigns = {},
            $mediaProvider = mQuery('#media_provider:first'),
            $campaignSettings = mQuery('#media_campaign_settings:first'),
            campaignSettings = $campaignSettings.val(),
            campaignsJSONEditor,
            $campaignsJSONEditor;
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:mauticMedia:getCampaignMap',
                mediaAccountId: Mautic.getEntityId(),
                mediaProvider: $mediaProvider.val(),
                campaignSettings: campaignSettings
            },
            dataType: 'json',
            cache: true,
            success: function (response) {
                if (typeof response.campaigns !== 'undefined') {
                    campaigns = response.campaigns;
                }
                if (typeof response.providerCampaigns !== 'undefined') {
                    providerCampaigns = response.providerCampaigns;
                }
                if (typeof response.campaignSettings !== 'undefined') {
                    var raw = JSON.stringify(response.campaignSettings, null, '  ');
                    $campaignSettings.val(raw);
                }
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            },
            complete: function (response) {

                // Grab the JSON Schema to begin rendering the form with
                // JSONEditor.
                mQuery.ajax({
                    dataType: 'json',
                    cache: true,
                    url: mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticMediaBundle/Assets/json/campaigns.json',
                    success: function (data) {
                        var schema = data;

                        if (campaigns.length) {
                            schema.definitions.campaign.properties.campaignId.enumSource[0].source = campaigns;
                        }

                        if (providerCampaigns.length) {
                            schema.definitions.campaign.properties.providerCampaignId.enumSource[0].source = providerCampaigns;
                        }

                        // Create our widget container for the JSON Editor.
                        $campaignsJSONEditor = mQuery('<div>', {
                            class: 'media_jsoneditor'
                        }).insertBefore($campaigns);

                        // Instantiate the JSON Editor based on our schema.
                        campaignsJSONEditor = new JSONEditor($campaignsJSONEditor[0], {
                            schema: schema,
                            disable_collapse: true,
                            disable_array_add: true,
                            disable_array_reorder: true,
                            disable_array_delete: true
                        });

                        $campaigns.change(function () {
                            // Load the initial value if applicable.
                            var raw = mQuery(this).val(),
                                obj;
                            if (raw.length) {
                                try {
                                    obj = mQuery.parseJSON(raw);
                                    if (typeof obj === 'object') {
                                        campaignsJSONEditor.setValue(obj);
                                    }
                                }
                                catch (e) {
                                    console.warn(e);
                                }
                            }
                        }).trigger('change');

                        // Persist the value to the JSON Editor.
                        campaignsJSONEditor.on('change', function (event) {
                            var obj = campaignsJSONEditor.getValue();
                            if (typeof obj === 'object') {
                                var raw = JSON.stringify(obj, null, '  ');
                                if (raw.length) {
                                    // Set the textarea.
                                    $campaigns.val(raw);
                                }
                            }
                            // Clickable Campaign headers.
                            $campaignsJSONEditor.find('div[data-schematype="string"][data-schemapath*=".campaignId"] .control-label').each(function () {
                                var campaignForLabel = mQuery(this).parent().find('select:first').val();
                                var label = 'Campaign';

                                if (null !== campaignForLabel && 0 < campaignForLabel) {
                                    label += ' ' + campaignForLabel;

                                    mQuery(this).html('<a href="' + mauticBasePath + '/s/campaigns/edit/' + campaignForLabel + '" target="_blank">' + label + '</a>');
                                }

                            });
                            var mediaProvider = $mediaProvider.val();
                            $campaignsJSONEditor.find('div[data-schematype="string"][data-schemapath*=".providerCampaignId"] .control-label').each(function () {
                                var campaignForLabel = mQuery(this).parent().find('select:first').val();
                                switch (mediaProvider) {
                                    case 'facebook':
                                        mQuery(this).html('<a href="https://www.facebook.com/adsmanager/manage/campaigns?act=' + campaignForLabel.replace('act_', '') + '" target="_blank">' + $(this).text() + '</a>');
                                        break;
                                }
                            });
                        });

                        $campaignsJSONEditor.show();
                    }
                });

            }
        });
    }
};

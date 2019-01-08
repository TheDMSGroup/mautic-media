// Campaigns field.
Mautic.mediaCampaigns = function () {
    var $campaigns = mQuery('#media_campaign_settings:first:not(.campaigns-checked)');
    if ($campaigns.length) {
        $campaigns.addClass('campaigns-checked');
        // Retrieve the list of available campaigns via Ajax
        var campaigns = {},
            providerCampaigns = {},
            campaignsJSONEditor,
            $campaignsJSONEditor;
        mQuery.ajax({
            url: mauticAjaxUrl,
            type: 'POST',
            data: {
                action: 'plugin:mauticMedia:getCampaignMap',
                mediaAccountId: Mautic.getEntityId(),
                mediaProvider: mQuery('#media_provider:first').val(),
                campaignSettings: mQuery('#media_campaign_settings:first').val()
            },
            dataType: 'json',
            cache: true,
            success: function (response) {
                if (typeof response.campaigns !== 'undefined') {
                    schema.definitions.campaign.properties.campaignId.enumSource[0].source = campaigns;
                }
                if (typeof response.providerCampaigns !== 'undefined') {
                    schema.definitions.campaign.properties.providerCampaignId.enumSource[0].source = providerCampaigns;
                }
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            },
            complete: function () {

                // Grab the JSON Schema to begin rendering the form with
                // JSONEditor.
                mQuery.ajax({
                    dataType: 'json',
                    cache: true,
                    url: mauticBasePath + '/' + mauticAssetPrefix + 'plugins/MauticMediaBundle/Assets/json/campaigns.json',
                    success: function (data) {
                        var schema = data;

                        if (campaigns.length) {
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
                            disable_array_reorder: true
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
                        });

                        $campaignsJSONEditor.show();
                    }
                });

            }
        });
    }
};

{extends file='parent:frontend/register/index.tpl'}


{block name='frontend_register_billing_fieldset_input_country_states' append}
    <div class="newsletter-option clearfix">
        <input type="checkbox" name="register[newsletter][subscribe]" id="register_newsletter_subscribe" value="1" />
        <label class="left-align" for="register_newsletter_subscribe">
            {s name="RegisterSubscribeForNewsletter" namespace="frontend/plugins/OptivoBroadmail"}Ja, informieren Sie mich bitte per E-Mail über Neuigkeiten. Newsletterabmeldung jederzeit möglich!{/s}
        </label>
    </div>
{/block}
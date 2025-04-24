{extends file="parent:frontend/account/index.tpl"}

{block name='frontend_index_content'}
    <div class="account--success">{debug}
        {if $recipientSuccessfulUnsubscribed}
            {include file="frontend/_includes/messages.tpl" type='success' content="{s namespace="frontend/plugins/OptivoBroadmail" name="RecipientSuccessfulUnsubscribed"}Sie wurden vom Newsletter abgemeldet{/s}"}
        {/if}
    </div>
{/block}
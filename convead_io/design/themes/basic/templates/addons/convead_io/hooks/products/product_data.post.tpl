<script type="text/javascript">
    {if $runtime.controller eq 'products'}
        if (typeof cnv_view_product == 'undefined' && typeof convead != 'undefined') convead('event', 'view_product', {
            {if $product.product_code}
            product_id: '{$product.product_id}',
            {else}
            product_id: '{$obj_prefix}{$obj_id}',
            {/if}
            product_name: '{$product.product}',
            product_url: '{$seo_canonical.current}'
         });
         var cnv_view_product = true;
    {/if}
</script>

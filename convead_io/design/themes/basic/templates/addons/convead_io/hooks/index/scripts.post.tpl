<!-- ConveadWidget -->
<script type="text/javascript">
var app_key = "{$addons.convead_io.convead_io_api_key}";
var json_companies = '{$addons.convead_io.convead_io_companies}'.split('&quot;').join('"');
var company_id = {$runtime.company_id};
if (json_companies) {
  var companies = JSON.parse(json_companies);
  if (companies && companies[company_id]) app_key = companies[company_id];
}

if (app_key) {

  window.ConveadSettings = {
    {if $auth.user_id }
    visitor_uid: '{$auth.user_id}',
    visitor_info: {
        {if $user_info.firstname }first_name: '{$user_info.firstname}',{/if}
        {if $user_info.lastname}last_name: '{$user_info.lastname}',{/if}
        {if $user_info.email}email: '{$user_info.email}',{/if}
        {if $user_data.s_phone}phone:{$user_data.s_phone},{/if}
        {if $user_data.date_of_birth}date_of_birth:{$user_data.date_of_birth},{/if}
        {if $user_data.gender}date_of_birth:{$user_data.gender}{/if}
    },
    {/if}
    app_key: app_key

  };

  (function(w,d,c){
    w[c]=w[c]||function(){
        (w[c].q=w[c].q||[]).push(arguments)};
    var ts = (+new Date()/86400000|0)*86400;
    var s = d.createElement('script');
    s.type = 'text/javascript';
    s.async = true;
    s.src = '//tracker.convead.io/widgets/'+ts+'/widget-'+app_key+'.js';
    var x = d.getElementsByTagName('script')[0];
    x.parentNode.insertBefore(s, x);
  })(window,document,'convead');

  jQuery(document).ready(
    function(){
      jQuery(document).on(
        'click',
        '.checkbox.cm-news-subscribe',
        function(){
          if(jQuery(this).prop('checked')){
            convead(
              'event',
              'custom',
              {
                key: 'news_subscribe'
              }
            );
          }
        }
      );
    }
  );

}
</script>
<!-- /Convead Widget -->

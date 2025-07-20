(function (wp, $) {
  'use strict';

  var media;
  $(document).on('click', '.upload-btn', function (e) {
      e.preventDefault();
      if (media) {
          media.open();
          return;
      }
      var that = $(this);
      media = wp.media({title: 'Choose an image', multiple: false})
      .open()
      .on('select', function () {
          var obj = media.state().get('selection').first().toJSON();
          if(obj.type == 'image'){
            var thumbnail = obj.sizes.thumbnail || obj.sizes.full;
            that.siblings('img').attr('src', thumbnail.url);
            that.prev().val(obj.id);
          }else{
            that.siblings('input').val(obj.url);
          }
      });
  });

  // orderby
  function hook_orderby(data){
    var d = [
        { label: 'User', value: 'user' },
        { label: 'User played', value: 'user_played' },
        { label: 'User likes', value: 'user_likes' },
        { label: 'User following', value: 'user_following' },
        { label: 'User verified', value: 'user_verified' },
        { label: 'All count', value: 'all' },
        ];

    if(typeof count_play !== 'undefined'){
      if(count_play.indexOf('year') !== -1){
        d.push({ label: 'Yearly count', value: 'year' });
      }
      if(count_play.indexOf('month') !== -1){
        d.push({ label: 'Monthly count', value: 'month' });
      }
      if(count_play.indexOf('week') !== -1){
        d.push({ label: 'Weekly count', value: 'week' });
      }
      if(count_play.indexOf('day') !== -1){
        d.push({ label: 'Daily count', value: 'day' });
      }
    };

    d = wp.hooks.applyFilters('hook_loop_orderby', d);
    data = data.concat(d);
    return data;
  }
  (typeof wp !== 'undefined' ) && (typeof wp.hooks !== 'undefined' ) && wp.hooks.addFilter('hook_orderby', 'loopblock', hook_orderby);

})(window.wp, jQuery);

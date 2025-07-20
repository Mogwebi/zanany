window.playlist = false;

(function ($) {
  "use strict";

  // start player
  $(document).on('click.play', '.btn-play, .btn-play-now, .btn-next-play, .btn-queue', function(e){
    e.preventDefault();
    createPlyr([]);

    if(!playlist) return;

    var id = $(this).closest('[data-play-id]').attr('data-play-id') || $(this).attr('data-user-id'),
        type = $(this).attr('data-user-id') ? 'user' : 'post',
        from = $(this).closest('.is-album').attr('data-play-id') || $(this).closest('.is-playlist').attr('data-play-id'),
        index = $(this).attr('data-index') || 0,
        ids = [];

    // pause on single/playlist/album/auto if it's playing
    if( $(this).hasClass('active') ){
        playlist.pause();
        return;
    }

    // play in short
    if( $(this).closest('.block-loop-short-item').length > 0 ){
      playlist.repeat = 2;
      $('.plyr-playlist').addClass('plyr-short');
      resizeShortPlay();
    }else{
      $('.plyr-playlist').removeClass('plyr-short');
    }
    
    if(type == 'post' && playlist.getIndex(id) > -1){
      playlist.play({id: id}, index);
    }else{
      var url = play.rest.endpoints.play + '/' + id;
      var data = {
          type: type
      };
      
      // single track from album / playlist
      if($(this).closest('.album-tracks').length && from){
        data.from = from;
        index = $(this).closest('.block-loop-item').index();
      }

      // auto playlist 
      if($('.btn-play[data-play-id='+id+']').hasClass('btn-play-auto') || ($(this).closest('.album-tracks').length && $('.header-station .btn-play-auto').length ) ){
        var ids = [];
        $('.album-track').each(function(key, item){
          ids.push( parseInt( $(item).attr('data-play-id') ) );
        });
        data.ids = ids;
      }
      
      $.ajax({
        url: url,
        type: 'get',
        datatype: 'json',
        data: data,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', play.nonce);
        }
      }).then(
        function(data){
          playlist.play(data, index);
        }
      );
    }
  });

  function createPlyr(items){
    if(playlist) return playlist;
    if(!play.is_user_logged_in && play.login_to_play == '1'){
      $(document).trigger('require_login');
      return;
    }
    var play_el = $('#plyr-playlist');
    if(play_el.length == 0){
      play_el = $('<div class="plyr-playlist player fixed-bottom" id="plyr-playlist"></div>');
      play_el.appendTo('body');
    }
    play_el.append('<audio playsinline id="player"></audio>');
    $('html').addClass('open-player');
    playlist = new Playlist(
      {
        playlist: '#plyr-playlist', 
        player: '#player'
      },
      items,
      {
        theme: play.player_theme,
        timeoutCount: play.rest.timeout_count,
        iconUrl: play.url + 'libs/plyr/plyr.svg',
        blankVideo: play.url + 'libs/plyr/blank.mp4',
        history: play.player_history,
        autonext: play.player_autonext,
        adsInterval: play.ad_interval ? play.ad_interval : 3,
        ads: {
          enabled: play.ad_tagurl ? true : false,
          tagUrl: play.ad_tagurl
        },
        keyboard: {
          global: true,
        },
        i18n: play.i18n,
        autoplay: false,
        playsinline: true
      }
    );

    playlist.player.on('timeupdate', function(e){
      // update the waveform
      var item = playlist.getCurrent();
      if(!item) return;
      var percent = playlist.player.currentTime / playlist.player.duration;
      var waves = $('.waveform .waveform_wrap');
      var wave = $('[data-id="'+item.id+'"].waveform_wrap');
      waves.not(wave).trigger('timeupdate', 0);
      wave && percent && wave.trigger('timeupdate', [percent, playlist.player.paused]);
    });
    $(document).trigger('playlist.ready');
    return playlist;
  }

  function destroyPlyr(){
    // deactive all the active class
    $('[data-play-id]').removeClass('active');
    window.playlist.player.destroy();
    window.players = [];
    window.playlist = null;
    if($('#plyr-playlist').children().length > 0){
      $('#plyr-playlist').remove();
    }
  }

  $(document).on('playlist', function(e){
    createPlyr(play.default_id);
  });

  $(document).on('destroy.playlist', function(e){
    destroyPlyr();
  });

  $(document).on('player_preview_end', function(e){
    $(document).trigger('require_login');
  });
  
  // waveform
  function waveform(){
    $('.waveform').each(function(){
      var $this = $(this);
      var $color = $this.css('color');
      var $data = $this.attr('data-waveform');
      if(!$data) return;

      $data = eval('['+$data+']');
      var opt = {container:$this.find('.waveform-container'), id:$this.attr('data-id'), duration: $this.attr('data-duration')};
      var wf = new Waveform( $.extend(true, {}, opt, play.waveform_option) );
      wf.load($data);
      
      // update the player 
      $(wf.wrap).on('update', function(e, percent, id){
        if(!playlist) return;
        var item = playlist.getCurrent();
        if(id == item.id){
          playlist.player.currentTime = playlist.player.duration * percent;
        }
      });

      // remove data attribute
      $this.removeAttr('data-waveform');
    });
  }

  // init
  function init(){
    $.fn.popover && $('[data-toggle="popover"]').popover();
    $.fn.tooltip && $('[data-toggle="tooltip"]').tooltip();
    
    waveform();
    shortPlay();
  }
  
  $(document).on('pjax:end, refresh', function(e){
    init();
  });

  // load history
  $(window).on('load', function() {
    init();
    try{
      if( $('.no-player').length > 0 ){
        return;
      }
      // play default
      if(play.default_id){
        var pl = createPlyr(play.default_id);
        return;
      }
      // play history
      var data = localStorage.getItem('plyr');
      if(data){
        data = JSON.parse(data);
        if(data.items.length > 0 && play.player_history){
          var pl = createPlyr(data.items);
        }
      }
    }catch(err){
      
    }

    startShortPlay();
  });

  // like in player
  $(document).on('like.play', function(e, id, status, type){
    if(!playlist || type !== 'post') return;
    var item = playlist.getItem(id);
    if(item) item.like = status;
  });

  // auto get next
  $(document).on('complete.play', function(e, obj){
    var id = obj.ids.slice(-1).pop();
    var url = play.rest.endpoints.play + '/' + id;
    $.ajax({
      url: url,
      type: 'get',
      datatype: 'json',
      data:{
        type: 'next',
        ids: obj.ids
      }
    }).then(
      function(data){
        if(data !== false){
          playlist.play(data, 0);
        }
      }
    );
  });

  // played to count
  $(document).on('played', function(e, obj){
    var id = obj.id;
    var url = play.rest.endpoints.play + '/' + id;
    $.ajax({
      url: url,
      type: 'get',
      datatype: 'json',
      data: {
        type: 'played',
        nonce: play.nonce
      },
      beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', play.nonce);
      }
    });
  });

  // switch player
  $(document).on('pjax:complete', function() {
    var el = $('.block-loop-short-item');
    if( el.length == 0){
      $('.plyr-playlist').removeClass('plyr-short');
    }
  });

  // short play
  $(document).on('pjax:complete', function(e){
    startShortPlay();
  });

  function startShortPlay(){
    $('.block-loop-short-item').first().find('[data-play-id]').trigger('click');
  }

  function shortPlay(){
    var els = $('.block-loop-short-item'), scrollingClass = 'plyr-scrolling';
    if(els.length == 0) return;

    els.parent().css('height', els.parent().height());

    var container = window, style = getComputedStyle(els.parent()[0]), overflow = style.getPropertyValue('overflow-y');
    if(overflow == 'scroll' || overflow == 'auto'){
      container = els.parent()[0];
    }

    container.addEventListener('scroll', debounce(_inview, 100), false);
    container.addEventListener('scroll', _scroll);
    window.addEventListener('resize', debounce(resizeShortPlay, 100), false);

    function _inview(){
      for (var i = 0; i < els.length; i++){
        var inview = isVisible(els[i], container), el = els[i];
        if(inview && !el.inView){
          el.inView = true;
          // scroll to play
          $(els[i]).find('[data-play-id]').trigger('click');
          setTimeout(function(){
            resizeShortPlay();
            $('.plyr-short').removeClass(scrollingClass);}
            , 1000
          );
        }

        if(!inview && el.inView){
          el.inView = false;
        }
      }
    }

    function _scroll(){
      $('.plyr-short').addClass(scrollingClass);
    }
  }

  function resizeShortPlay(){
    var item = $('.block-loop-short-item');
    if(item.length == 0) return;
    var top = item.parent().offset().top, rect = item[0].getBoundingClientRect();
    $('.plyr-short').css('--top', top+'px').css('--left', rect.left+'px').css('--width', rect.width+'px').css('--height', rect.height+'px');
  }

  function isVisible(element, container, partial) {
    var eTop = element.offsetTop;
    var eBottom = eTop + element.clientHeight;

    var cTop = container.pageYOffset ? container.pageYOffset : container.scrollTop;
    var cBottom = cTop + (container.pageYOffset ? container.innerHeight : container.clientHeight);

    var isTotal = (eTop >= cTop && eBottom <= cBottom);
    var isPartial = partial && (
      (eTop < cTop && eBottom > cTop) ||
      (eBottom > cBottom && eTop < cBottom)
    );
    return  (isTotal  || isPartial);
  };

  function debounce(func, wait, immediate) {
    var timeout, result;

    return function() {
      var context = this, args = arguments, later, callNow;

      later = function() {
        timeout = null;
        if (!immediate) { result = func.apply(context, args); }
      };

      callNow = immediate && !timeout;

      clearTimeout(timeout);
      timeout = setTimeout(later, wait);

      if (callNow) { result = func.apply(context, args); }

      return result;
    };
  };

})(jQuery);

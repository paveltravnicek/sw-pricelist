(function($){
  function uid(prefix){
    return prefix + '_' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(16) + Math.random().toString(16).slice(2)));
  }

  function slugify(value){
    return (value || '')
      .toString()
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function getNextNumericKey(){
    var used = {};
    $('#swjc-lists > .swjc-list').each(function(){
      var key = ($(this).find('.swjc-list__key').val() || '').trim();
      if (/^\d+$/.test(key)) {
        used[parseInt(key, 10)] = true;
      }
    });
    var i = 1;
    while (used[i]) i++;
    return String(i);
  }

  function updateAccordionTitles(){
    $('#swjc-lists > .swjc-list').each(function(){
      var $list = $(this);
      var title = ($list.find('.swjc-list__title').val() || '').trim() || 'Ceník bez názvu';
      var key = ($list.find('.swjc-list__key').val() || '').trim() || '1';
      $list.find('> .swjc-list__top h3').text(title);
      $list.find('> .swjc-list__top .swjc-muted').text('Shortcode klíč: ' + key);
      $list.find('.swjc-shortcode-box code').text('[sw_cenik key="' + key + '"]');
      $list.find('.swjc-categories > .swjc-category').each(function(){
        var $cat = $(this);
        var catTitle = ($cat.find('.swjc-category__title').val() || '').trim() || 'Kategorie bez názvu';
        $cat.find('> .swjc-category__header .swjc-category__summary-title').text(catTitle);
      });
    });
  }

  function rebuildDuplicateSelect(){
    var $select = $('#swjc-duplicate-source');
    if (!$select.length) return;
    var current = $select.val();
    $select.find('option:not(:first)').remove();
    $('#swjc-lists > .swjc-list').each(function(){
      var id = $(this).attr('data-list-id');
      var title = ($(this).find('.swjc-list__title').val() || '').trim() || 'Ceník bez názvu';
      $('<option>').val(id).text(title).appendTo($select);
    });
    $select.val(current);
  }

  function rebuildPayload(){
    var lists = [];
    $('#swjc-lists > .swjc-list').each(function(listIndex){
      var $list = $(this);
      var listId = $list.attr('data-list-id') || uid('list');
      var title = ($list.find('.swjc-list__title').val() || '').trim();
      var keyInput = ($list.find('.swjc-list__key').val() || '').trim();
      var key = keyInput === '' ? String(listIndex + 1) : slugify(keyInput);
      var currency = ($list.find('.swjc-list__currency').val() || '').trim();
      var fromLabel = ($list.find('.swjc-list__from').val() || '').trim();

      var categories = [];
      $list.find('.swjc-categories > .swjc-category').each(function(catIndex){
        var $cat = $(this);
        var catId = $cat.attr('data-cat-id') || uid('cat');
        var catTitle = ($cat.find('.swjc-category__title').val() || '').trim();

        var items = [];
        $cat.find('tbody.swjc-items > tr.swjc-item').each(function(itemIndex){
          var $item = $(this);
          var itemId = $item.attr('data-item-id') || uid('item');
          items.push({
            id: itemId,
            name: ($item.find('.swjc-item__name').val() || '').trim(),
            note: ($item.find('.swjc-item__note').val() || '').trim(),
            price: ($item.find('.swjc-item__price').val() || '').trim(),
            show_from: $item.find('.swjc-item__show-from-toggle').is(':checked') ? 1 : 0,
            order: itemIndex
          });
        });

        categories.push({
          id: catId,
          title: catTitle,
          order: catIndex,
          items: items
        });
      });

      lists.push({
        id: listId,
        title: title,
        key: key,
        currency: currency,
        from_label: fromLabel,
        order: listIndex,
        categories: categories
      });
    });

    $('#swjc-payload').val(JSON.stringify(lists));
    updateAccordionTitles();
    rebuildDuplicateSelect();
  }

  function initItemSortable($tbody){
    $tbody.sortable({
      handle: '.swjc-item__drag .swjc-handle',
      axis: 'y',
      placeholder: 'swjc-sort-placeholder-row',
      forcePlaceholderSize: true,
      update: rebuildPayload
    });
  }

  function initCategorySortable($container){
    $container.sortable({
      handle: '> .swjc-category > summary .swjc-handle',
      items: '> .swjc-category',
      placeholder: 'swjc-sort-placeholder',
      forcePlaceholderSize: true,
      update: rebuildPayload
    });
  }

  function initSortables(){
    $('#swjc-lists').sortable({
      handle: '> .swjc-list > summary .swjc-handle',
      items: '> .swjc-list',
      placeholder: 'swjc-sort-placeholder',
      forcePlaceholderSize: true,
      update: rebuildPayload
    });

    $('.swjc-categories').each(function(){ initCategorySortable($(this)); });
    $('.swjc-items').each(function(){ initItemSortable($(this)); });
  }

  function addList(prefill){
    var tpl = $('#tpl-swjc-list').html();
    var newId = uid('list');
    tpl = tpl.replaceAll('list___NEW__', newId);
    var $el = $(tpl);
    $el.attr('data-list-id', newId);
    if (prefill) {
      $el.find('.swjc-list__title').val(prefill.title || '');
      $el.find('.swjc-list__key').val(prefill.key || getNextNumericKey());
      $el.find('.swjc-list__currency').val(prefill.currency || 'Kč');
      $el.find('.swjc-list__from').val(prefill.from_label || 'od');
      var $categories = $el.find('.swjc-categories').empty();
      (prefill.categories || []).forEach(function(cat){
        var $cat = addCategory($el, cat, true);
        $categories.append($cat);
      });
    } else {
      $el.find('.swjc-list__key').val(getNextNumericKey());
    }
    $('#swjc-lists').append($el);
    initCategorySortable($el.find('.swjc-categories'));
    $el.attr('open', 'open');
    rebuildPayload();
    return $el;
  }

  function addCategory($list, prefill, returnOnly){
    var listId = $list.attr('data-list-id');
    var tpl = $('#tpl-swjc-category').html();
    var newId = uid('cat');
    tpl = tpl.replaceAll('cat___NEW__', newId).replaceAll('__LISTID__', listId);
    var $el = $(tpl);
    $el.attr('data-cat-id', newId);
    if (prefill) {
      $el.find('.swjc-category__title').val(prefill.title || '');
      $el.find('.swjc-items').empty();
      (prefill.items || []).forEach(function(item){
        var $item = addItem($el, item, true);
        $el.find('.swjc-items').append($item);
      });
    }
    initItemSortable($el.find('.swjc-items'));
    if (returnOnly) return $el;
    $list.find('.swjc-categories').append($el);
    $el.attr('open', 'open');
    rebuildPayload();
    return $el;
  }

  function addItem($category, prefill, returnOnly){
    var listId = $category.closest('.swjc-list').attr('data-list-id');
    var catId = $category.attr('data-cat-id');
    var tpl = $('#tpl-swjc-item').html();
    var newId = uid('item');
    tpl = tpl.replaceAll('item___NEW__', newId).replaceAll('__LISTID__', listId).replaceAll('__CATID__', catId);
    var $el = $(tpl);
    $el.attr('data-item-id', newId);
    if (prefill) {
      $el.find('.swjc-item__name').val(prefill.name || '');
      $el.find('.swjc-item__note').val(prefill.note || '');
      $el.find('.swjc-item__price').val(prefill.price || '');
      $el.find('.swjc-item__show-from-toggle').prop('checked', prefill.show_from !== 0);
    }
    if (returnOnly) return $el;
    $category.find('.swjc-items').append($el);
    rebuildPayload();
    return $el;
  }

  function cloneListData($list){
    var data = {
      title: (($list.find('.swjc-list__title').val() || '').trim() || 'Ceník') + ' (kopie)',
      key: getNextNumericKey(),
      currency: ($list.find('.swjc-list__currency').val() || '').trim(),
      from_label: ($list.find('.swjc-list__from').val() || '').trim(),
      categories: []
    };
    $list.find('.swjc-categories > .swjc-category').each(function(){
      var $cat = $(this);
      var cat = {
        title: ($cat.find('.swjc-category__title').val() || '').trim(),
        items: []
      };
      $cat.find('tbody.swjc-items > tr.swjc-item').each(function(){
        var $item = $(this);
        cat.items.push({
          name: ($item.find('.swjc-item__name').val() || '').trim(),
          note: ($item.find('.swjc-item__note').val() || '').trim(),
          price: ($item.find('.swjc-item__price').val() || '').trim(),
          show_from: $item.find('.swjc-item__show-from-toggle').is(':checked') ? 1 : 0
        });
      });
      data.categories.push(cat);
    });
    return data;
  }

  $(function(){
    initSortables();
    rebuildPayload();

    $('#swjc-add-list').on('click', function(){ addList(); });

    $('#swjc-duplicate-list').on('click', function(){
      var selectedId = $('#swjc-duplicate-source').val();
      if (!selectedId) {
        window.alert('Vyberte ceník, který chcete duplikovat.');
        return;
      }
      var $source = $('#swjc-lists > .swjc-list[data-list-id="' + selectedId + '"]');
      if (!$source.length) return;
      addList(cloneListData($source));
    });

    $(document).on('click', '.swjc-duplicate-this-list', function(e){
      e.preventDefault();
      e.stopPropagation();
      addList(cloneListData($(this).closest('.swjc-list')));
    });

    $(document).on('click', '.swjc-delete-list', function(e){
      e.preventDefault();
      e.stopPropagation();
      if(!window.confirm('Opravdu smazat celý ceník?')) return;
      $(this).closest('.swjc-list').remove();
      rebuildPayload();
    });

    $(document).on('click', '.swjc-add-category', function(e){
      e.preventDefault();
      e.stopPropagation();
      addCategory($(this).closest('.swjc-list'));
    });

    $(document).on('click', '.swjc-delete-category', function(e){
      e.preventDefault();
      e.stopPropagation();
      if(!window.confirm('Opravdu smazat kategorii?')) return;
      $(this).closest('.swjc-category').remove();
      rebuildPayload();
    });

    $(document).on('click', '.swjc-add-item', function(e){
      e.preventDefault();
      e.stopPropagation();
      addItem($(this).closest('.swjc-category'));
    });

    $(document).on('click', '.swjc-delete-item', function(){
      if(!window.confirm('Smazat tuto položku?')) return;
      $(this).closest('.swjc-item').remove();
      rebuildPayload();
    });

    $(document).on('input', '.swjc-list__title', rebuildPayload);

    $(document).on('input', '.swjc-list__key', function(){
      $(this).val(slugify($(this).val()));
      rebuildPayload();
    });

    $(document).on('input', '.swjc-list__currency, .swjc-list__from, .swjc-category__title, .swjc-item__name, .swjc-item__note, .swjc-item__price', rebuildPayload);
    $(document).on('change', '.swjc-item__show-from-toggle', rebuildPayload);

    $(document).on('click', '.swjc-list summary button, .swjc-category summary button', function(e){
      e.preventDefault();
      e.stopPropagation();
    });

    $('#swjc-form').on('submit', rebuildPayload);
  });
})(jQuery);

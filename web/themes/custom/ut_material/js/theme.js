// eslint-disable-next-line
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.ut_material_Functions = {
    // eslint-disable-next-line
    attach: (context, settings) => {
      // Put your common functions and handlers here. For example:
      //
      // const myCommonFunction = (element, event) => {
      //   // Do something.
      // }
      // Put global page behaviors here.
      // It is Drupal's equivalent JQuery's $(document).ready(myInit());
      // Example:
      //
      // once('myGlobalBehaviors', 'html').forEach(() => {
      //   const singleElement = document.getElementById('element-id');
      //   singleElement.addEventListener('click', event => myCommonFunction(singleElement, event));
      //
      //   const multipleElements = document.querySelectorAll('.classname-selector');
      //   multipleElements.forEach(element => {
      //     element.addEventListener('click', event => myCommonFunction(element, event));
      //   });
      // });
      // Put your specific behaviors with Ajax loading support here. For example:
      //
      // once('mySpecificBehavior', '.classname-selector', context).forEach(element => {
      //   element.addEventListener('click', event => myCommonFunction(element, event));
      // });



      // ----------------------- Begin Code for Main Nav Menu ------------------

      const navBlocks = once('menu-init', '#block-ut-material-main-menu', context);

      navBlocks.forEach((navBlock) => {
        const navMenu = navBlock.querySelector('.navbar-menu');

        if (!navMenu) return;

        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'menu-toggle';
        toggleBtn.setAttribute('type', 'button');
        toggleBtn.setAttribute('aria-label', 'Toggle navigation');
        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.innerHTML = '<span>Menu ☰</span>';
        navBlock.insertBefore(toggleBtn, navMenu);

        // --- Helper Function to Close Menu ---
        const closeMenu = () => {
          navMenu.classList.remove('is-open');
          toggleBtn.classList.remove('is-active');
          toggleBtn.setAttribute('aria-expanded', 'false');
        };

        // 1. Toggle on Click
        toggleBtn.addEventListener('click', (e) => {
          e.stopPropagation(); // Prevent immediate close from the "click anywhere" listener
          const isOpen = navMenu.classList.toggle('is-open');
          toggleBtn.setAttribute('aria-expanded', isOpen);
          toggleBtn.classList.toggle('is-active');
        });

        // 2. Click Outside Logic
        document.addEventListener('click', (e) => {
          // If the menu is open and the click is NOT inside the navBlock, close it
          if (navMenu.classList.contains('is-open') && !navBlock.contains(e.target)) {
            closeMenu();
          }
        });
      });
      // ----------------------- End Code for Main Nav Menu --------------------

      // ----------------------- Begin Code for Collapsing Site Title Bar ------

      // Ties the title bar's collapse directly to scroll position (via
      // --title-collapse-progress, read by navigation.scss) instead of
      // toggling a class past a threshold. A threshold toggle collapses on
      // its own CSS-transition timeline regardless of how fast the user is
      // scrolling, which reads as a sudden jump rather than something
      // following the scroll — scrubbing the value every frame keeps the
      // collapse moving at the same rate as the scroll itself.
      once('site-title-bar-collapse', '#site-title-bar', context).forEach((titleBar) => {
        // Distance, in px of scroll, over which the title bar fully
        // collapses. Matches its own max-height (see navigation.scss) so
        // it visually finishes collapsing right as its height reaches 0,
        // rather than finishing early/late relative to the scroll.
        const COLLAPSE_DISTANCE = 96;
        let ticking = false;
        const updateTitleBar = () => {
          const progress = Math.min(1, Math.max(0, window.scrollY / COLLAPSE_DISTANCE));
          titleBar.style.setProperty('--title-collapse-progress', progress);
          // The navbar's logo (region--navbar.html.twig) lives outside
          // #site-title-bar entirely — a sibling, not a descendant — so it
          // can't read a CSS var set only on this element; CSS custom
          // properties only cascade down the DOM tree from where they're
          // set, not sideways. Setting it again on <html> makes it
          // available to grow/shrink the logo (navigation.scss) in step
          // with this same scroll-driven value, with no separate JS needed
          // for that element.
          document.documentElement.style.setProperty('--title-collapse-progress', progress);
          ticking = false;
        };
        updateTitleBar();
        window.addEventListener('scroll', () => {
          // Coalesce rapid scroll events to one update per frame — scroll
          // fires far more often than the page can usefully repaint.
          if (!ticking) {
            ticking = true;
            requestAnimationFrame(updateTitleBar);
          }
        }, { passive: true });
      });

      // ----------------------- End Code for Collapsing Site Title Bar --------

      // ----------------------- Begin Code for Toolbar Offset -----------------

      // How much fixed, top-anchored admin chrome (core toolbar bar, GIN's
      // front-end secondary toolbar) sits above the navbar varies by user:
      // it depends on which toolbar items and local tasks THIS user has
      // permission to see, not just whether they have toolbar access at all.
      // Measuring it directly avoids hardcoding pixel values tuned for one
      // account's permissions that leave a gap (or an overlap) for everyone
      // else.
      //
      // Two different offsets are needed, because "how much space does this
      // chrome need" depends on what's asking:
      // - --navbar-toolbar-offset (header's one-time margin-top): only
      //   chrome that is position: fixed needs this, because fixed chrome
      //   sits outside normal document flow and would otherwise overlap the
      //   header. Sticky chrome (see below) already pushes the header down
      //   on its own via flow and must NOT be added here too, or the header
      //   ends up pushed down twice.
      // - --navbar-sticky-offset (menu bar's sticky `top`): needs the total
      //   height of everything still visually pinned to the top of the
      //   viewport once the page is scrolled — fixed chrome (always pinned)
      //   AND sticky chrome (re-pins to the top once scrolled past), since
      //   both remain on screen above the menu bar and would otherwise cover
      //   it once it goes sticky too.
      once('navbar-toolbar-offset', 'body', context).forEach(() => {
        const updateOffset = () => {
          let fixedOffset = 0;

          // The GIN admin theme collapses .toolbar-bar's own box to
          // height: 0 by design (its icons/menu render via absolutely
          // positioned children that overflow that 0-height box), so
          // measuring it directly reads as 0 even when a toolbar is
          // visible. Prefer GIN's own --gin-toolbar-height custom property
          // (its intended height) when a toolbar is present at all; fall
          // back to a direct measurement for non-GIN admin themes. This bar
          // is always position: fixed (core toolbar and GIN both render it
          // that way), so its height counts toward both offsets below.
          const toolbarBar = document.querySelector('.toolbar .toolbar-bar');
          if (toolbarBar) {
            const ginToolbarHeight = parseFloat(
              getComputedStyle(document.documentElement).getPropertyValue('--gin-toolbar-height'),
            );
            fixedOffset += Number.isFinite(ginToolbarHeight) && ginToolbarHeight > 0
              ? ginToolbarHeight
              : toolbarBar.getBoundingClientRect().height;
          }

          // GIN's secondary (local tasks) toolbar is only present at all
          // when the current user has local task links to show, and it
          // renders as `position: sticky` as the first child of <body> —
          // unlike the fixed main toolbar bar above, it already pushes the
          // header down by its own height via normal document flow, so its
          // height must stay out of the margin-top offset (that would
          // double it). It DOES need to count toward the sticky-menu
          // offset, though: once scrolled, it re-pins to the top of the
          // viewport just like fixed chrome does, and the menu bar has to
          // stick below it or it renders hidden underneath.
          const ginSecondaryToolbar = document.querySelector('.gin-secondary-toolbar--frontend');
          const secondaryToolbarHeight = ginSecondaryToolbar
            ? ginSecondaryToolbar.getBoundingClientRect().height
            : 0;

          document.documentElement.style.setProperty('--navbar-toolbar-offset', `${fixedOffset}px`);
          document.documentElement.style.setProperty(
            '--navbar-sticky-offset',
            `${fixedOffset + secondaryToolbarHeight}px`,
          );
        };
        updateOffset();
        window.addEventListener('resize', updateOffset, { passive: true });

        // GIN's toolbar.js and escape_admin.js run their own async setup
        // (see themes/contrib/gin/dist/js/toolbar.js) that can add, remove,
        // or resize the secondary toolbar after this behavior's initial
        // synchronous measurement — a stale --navbar-toolbar-offset from
        // before that settles is exactly what leaves an unexplained gap (or
        // overlap) above the navbar. Watch the whole body (the secondary
        // toolbar isn't guaranteed to live inside #toolbar-administration)
        // and re-measure whenever anything in the toolbar area changes,
        // instead of trusting one snapshot taken at load.
        new MutationObserver(updateOffset).observe(document.body, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ['class', 'style'],
        });
      });

      // ----------------------- End Code for Toolbar Offset -------------------

    },
  };
})(Drupal, once);




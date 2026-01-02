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
        let hoverTimer; // Variable to store the 3-second timer

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
          clearTimeout(hoverTimer);
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

        // 3. Hover Timeout Logic (3 Seconds)
        navBlock.addEventListener('mouseleave', () => {
          // Start counting when the mouse leaves the menu area
          if (navMenu.classList.contains('is-open')) {
            hoverTimer = setTimeout(() => {
              closeMenu();
            }, 3000); // 3000ms = 3 seconds
          }
        });

        navBlock.addEventListener('mouseenter', () => {
          // If the mouse comes back in, cancel the closing timer
          clearTimeout(hoverTimer);
        });
      });
      // ----------------------- End Code for Main Nav Menu --------------------

    },
  };
})(Drupal, once);




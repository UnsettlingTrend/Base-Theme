import * as React from 'react';
import * as ReactDOM from 'react-dom';
import * as ReactDOMClient from 'react-dom/client';
import * as jsxRuntime from 'react/jsx-runtime';

// Expose React and ReactDOM as globals for the editor/renderer IIFE bundles.
// Merge jsx-runtime exports (jsx, jsxs, Fragment) onto the React global
// so that `globals: { 'react/jsx-runtime': 'React' }` works in Rollup.
//
// IMPORTANT: Spread jsxRuntime first, then React, so that React's `default`
// export (the full React object with hooks) is not overwritten by the JSX
// runtime's `default` (which only contains jsx/jsxs/Fragment). CJS interop
// in bundled libraries (e.g. react-table) does `a = a.default` and expects
// the full React API including useRef, useState, etc.
const { default: _jsxDefault, ...jsxNamed } = jsxRuntime;
window.React = { ...React, ...jsxNamed };
window.ReactDOM = { ...ReactDOM, ...ReactDOMClient };

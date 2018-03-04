'use strict';

/*
* Require the path module
*/
const path = require('path');

/*
 * Require the Fractal module
 */
const fractal = module.exports = require('@frctl/fractal').create();

/*
 * Give your project a title.
 */
fractal.set('project.title', 'Warden');

/*
 * Tell Fractal where to look for components.
 */
fractal.components.set('path', path.join(__dirname, '../src/Deeson/WardenBundle/Resources/views'));

/*
 * Tell Fractal where to look for documentation pages.
 */
fractal.docs.set('path', path.join(__dirname, '../src/Deeson/WardenBundle/Resources/doc'));

/*
 * Tell the Fractal web preview plugin where to look for static assets.
 */
fractal.web.set('static.path', path.join(__dirname, '../src/Deeson/WardenBundle/public'));

const twigAdapter = require('@frctl/twig')({
    functions: {
        'asset': function (inputPath) {
            // console.log(this)
            // return this.components.path(inputPath)
        }
    }
})
fractal.components.engine(twigAdapter)
fractal.components.set('ext', '.html.twig')
import Page from '../../../../symfony-design-system/front/js/class/Page';
import hljs from 'highlight.js';

export default class extends Page {
  async pageReady() {
    this.el.querySelectorAll('.highlight-block').forEach((elCode) => {
      hljs.highlightElement(
        elCode as HTMLElement,
      );
    })
  }
}
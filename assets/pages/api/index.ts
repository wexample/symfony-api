import Page from '@wexample/symfony-loader/js/class/Page';
import Prism from 'prismjs';
import 'prismjs/components/prism-bash.min.js';

export default class extends Page {
  async pageReady() {
    this.el.querySelectorAll('.highlight-code pre').forEach((elCode) => {
      Prism.highlightElement(
        elCode as HTMLElement,
        Prism.languages.bash
      );
    })
  }
}

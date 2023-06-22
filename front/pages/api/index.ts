import Page from '../../../../symfony-design-system/front/js/class/Page';
import Prism from 'prismjs';
import 'prismjs/components/prism-bash.min.js';

export default class extends Page {
  async pageReady() {
    console.log(Prism.languages.bash);

    this.el.querySelectorAll('.highlight-code pre').forEach((elCode) => {
      Prism.highlightElement(
        elCode as HTMLElement,
        Prism.languages.bash
      );
    })
  }
}

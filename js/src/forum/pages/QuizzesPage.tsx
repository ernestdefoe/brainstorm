import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { listQuizzes } from '../../common/api';
import type { Quiz } from '../../common/api';
import QuizCard from '../components/QuizCard';
import Leaderboard from '../components/Leaderboard';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-gauntlet.forum.${key}`, params);

type Tab = 'new' | 'popular' | 'mine';

/** /quizzes — every quiz the reader may see, with the overall leaderboard alongside. */
export default class QuizzesPage extends Page {
  quizzes: Quiz[] = [];
  loading = true;
  next: number | null = null;
  canCreate = false;
  tab: Tab = 'new';
  query = '';
  searchTimer = 0;
  asked = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(extractText(t('index.title')));
    app.history.push('quizzes', extractText(t('index.title')));
    this.load();
  }

  load(more = false) {
    this.loading = true;
    const params: Record<string, unknown> = { filter: {} as Record<string, string> };
    if (this.tab === 'popular') params.sort = 'popular';
    if (this.tab === 'mine') (params.filter as any).mine = '1';
    if (this.query.trim()) (params.filter as any).q = this.query.trim();
    if (more && this.next) params.page = { offset: this.next };

    const ask = ++this.asked;
    listQuizzes(params).then(
      ({ data, meta }) => {
        // An older search answering late doesn't overwrite a newer one.
        if (ask !== this.asked) return;
        this.quizzes = more ? [...this.quizzes, ...data] : data;
        this.next = meta.next;
        this.canCreate = meta.canCreate;
        this.loading = false;
        m.redraw();
      },
      () => {
        this.loading = false;
        m.redraw();
      }
    );
  }

  switchTo(tab: Tab) {
    this.tab = tab;
    this.load();
  }

  view(): Mithril.Children {
    return (
      <PageStructure className="GauntletIndexPage" sidebar={() => <IndexSidebar />}>
        <div className="GauntletIndexPage-head">
          <h2 className="GauntletIndexPage-title">{t('index.title')}</h2>
          {this.canCreate ? (
            <LinkButton className="Button Button--primary" icon="fas fa-plus" href={app.route('gauntlet.new')}>
              {t('index.new')}
            </LinkButton>
          ) : null}
        </div>

        <div className="GauntletIndexPage-toolbar">
          <div className="GauntletTabs" role="tablist">
            {(['new', 'popular', ...(this.canCreate ? ['mine'] : [])] as Tab[]).map((tab) => (
              <Button
                className={'Button Button--flat' + (this.tab === tab ? ' active' : '')}
                role="tab"
                aria-selected={this.tab === tab}
                onclick={() => this.switchTo(tab)}
              >
                {t('index.tabs.' + tab)}
              </Button>
            ))}
          </div>
          <input
            className="FormControl GauntletIndexPage-search"
            type="search"
            placeholder={extractText(t('index.search'))}
            aria-label={extractText(t('index.search'))}
            value={this.query}
            oninput={(e: InputEvent) => {
              this.query = (e.target as HTMLInputElement).value;
              window.clearTimeout(this.searchTimer);
              this.searchTimer = window.setTimeout(() => this.load(), 300);
            }}
          />
        </div>

        <div className="GauntletIndexPage-body">
          <div className="GauntletIndexPage-list">
            {this.quizzes.map((quiz) => (
              <QuizCard key={quiz.id} quiz={quiz} />
            ))}
            {this.loading ? <LoadingIndicator display="block" /> : null}
            {!this.loading && !this.quizzes.length ? <p className="GauntletEmpty">{t(this.query ? 'index.no_results' : 'index.empty')}</p> : null}
            {!this.loading && this.next ? (
              <Button className="Button GauntletIndexPage-more" onclick={() => this.load(true)}>
                {t('index.more')}
              </Button>
            ) : null}
          </div>
          <aside className="GauntletIndexPage-board">
            <h3>{t('leaderboard.overall')}</h3>
            <Leaderboard limit={10} compact />
            <LinkButton className="Button Button--link" href={app.route('gauntlet.leaderboard')}>
              {t('leaderboard.see_all')}
            </LinkButton>
          </aside>
        </div>
      </PageStructure>
    );
  }
}

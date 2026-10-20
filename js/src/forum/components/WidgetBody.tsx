import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import { listQuizzes, quizUrl } from '../../common/api';
import type { Quiz } from '../../common/api';
import Leaderboard from './Leaderboard';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-gauntlet.forum.widget.${key}`, params);

/** The widget's contents, in its own chunk: the top quiz takers, or the newest quizzes. */
export default class WidgetBody extends Component<{ show: 'leaderboard' | 'latest'; limit: number }> {
  quizzes: Quiz[] | null = null;

  oninit(vnode: Mithril.Vnode<{ show: 'leaderboard' | 'latest'; limit: number }, this>) {
    super.oninit(vnode);
    if (this.attrs.show === 'latest') {
      listQuizzes({ filter: {} }).then(({ data }) => {
        this.quizzes = data;
        m.redraw();
      });
    }
  }

  view(): Mithril.Children {
    return (
      <div className="GauntletWidget">
        {this.attrs.show === 'latest' ? this.latest() : <Leaderboard limit={this.attrs.limit} compact />}
        <LinkButton className="Button Button--link GauntletWidget-more" href={app.route('gauntlet.index')}>
          {t('all')}
        </LinkButton>
      </div>
    );
  }

  latest(): Mithril.Children {
    if (!this.quizzes) return <LoadingIndicator display="block" size="small" />;
    if (!this.quizzes.length) return <p className="GauntletBoard-empty">{app.translator.trans('ernestdefoe-gauntlet.forum.index.empty')}</p>;

    return (
      <ul className="GauntletWidget-latest">
        {this.quizzes.slice(0, this.attrs.limit).map((quiz) => (
          <li>
            <Link href={quizUrl(quiz)}>{quiz.title}</Link>
            <span className="GauntletWidget-meta">
              {app.translator.trans('ernestdefoe-gauntlet.forum.facts.questions', { count: quiz.questionCount })}
            </span>
          </li>
        ))}
      </ul>
    );
  }
}

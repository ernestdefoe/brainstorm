import app from 'flarum/admin/app';

/*
 * Gauntlet's admin is its permissions and one setting; quizzes themselves
 * are written and managed on the forum, where they are played.
 */
app.initializers.add('ernestdefoe-gauntlet', () => {
  const t = (key: string) => app.translator.trans(`ernestdefoe-gauntlet.admin.${key}`);

  app.registry
    .for('ernestdefoe-gauntlet')
    .registerSetting({
      setting: 'ernestdefoe-gauntlet.show_nav',
      type: 'boolean',
      label: t('settings.show_nav'),
    })
    .registerPermission({ icon: 'fas fa-graduation-cap', label: t('permissions.view'), permission: 'gauntlet.view', allowGuest: true }, 'view')
    .registerPermission({ icon: 'fas fa-play', label: t('permissions.take'), permission: 'gauntlet.take' }, 'reply')
    .registerPermission({ icon: 'fas fa-pen-to-square', label: t('permissions.create'), permission: 'gauntlet.create' }, 'start')
    .registerPermission({ icon: 'fas fa-graduation-cap', label: t('permissions.manage'), permission: 'gauntlet.manage' }, 'moderate')
    .registerPermission({ icon: 'fas fa-award', label: t('permissions.reward'), permission: 'gauntlet.reward' }, 'moderate');
});

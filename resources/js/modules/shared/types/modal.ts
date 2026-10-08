import type { Component } from 'vue';

export type AppModalVariant = 'default' | 'warning' | 'danger' | 'success';

export type AppModalActionTone = 'primary' | 'danger' | 'neutral';

export type AppModalAction = {
  key: string;
  label: string;
  tone?: AppModalActionTone;
  icon?: Component;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  loading?: boolean;
  form?: string;
  autoClose?: boolean;
};

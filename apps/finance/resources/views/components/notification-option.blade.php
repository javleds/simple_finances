@props(['value' => false, 'label', 'description'])

@php
$baseButtonClasses = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2';
$baseChipClasses = 'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out';

$buttonVariants = [
    true => 'bg-teal-600',
    false => 'bg-gray-200',
];

$chipVariants = [
    true => 'translate-x-5',
    false => 'translate-x-0',
];

$buttonClasses = sprintf(
    '%s %s',
    $baseButtonClasses,
    $buttonVariants[$value],
);

$chipClasses = sprintf(
    '%s %s',
    $baseChipClasses,
    $chipVariants[$value],
);
@endphp

<div class="relative flex gap-3 pb-4 pt-2">
    <div class="min-w-0 flex-1 text-sm/6">
        <label for="comments" class="font-medium text-white">{{ $label }}</label>
        <p id="comments-description" class="text-gray-400">{{ $description }}</p>
    </div>
    <div class="flex shrink-0 items-center">
        <div class="flex items-center justify-between">
            <button type="button"
                    class="{{ $buttonClasses }}"
                    role="switch" aria-checked="false"
                    aria-labelledby="availability-label" aria-describedby="availability-description"
                    {{ $attributes }}>
                <span aria-hidden="true"
                      class="{{ $chipClasses }}"></span>
            </button>
        </div>
    </div>
</div>

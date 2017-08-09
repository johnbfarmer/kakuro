import React from 'react';

export default class Cell extends React.Component {
    constructor(props) {
        super(props);
        this.state = {};
        this.loadVals(props);

        this.loadVals = this.loadVals.bind(this);
        this.getClasses = this.getClasses.bind(this);
        this.setActive = this.setActive.bind(this);
    }

    componentDidUpdate(props) {
        var cell = props.cell;
        this.state.active = cell.active;
        this.state.choices = cell.choices;
        this.state.remove = [];
        if (this.state.editable) {
            this.state.display = cell.choices.join('');
        }
    }

    componentWillUpdate(props) {
        this.loadVals(props);
    }

    loadVals(props) {
        var cell = props.cell;
        var editable = cell.is_data;
        var display = cell.choices.join('');
        var label_v = '';
        var label_h = '';
        var sum_box = false;
        if (!editable) {
            label_v = cell.display[0] ? cell.display[0].toString() : '';
            label_h = cell.display[1] ? cell.display[1].toString() : '';
            if (label_h.length > 0 || label_v.length > 0) {
                sum_box = true;
            }
        }

        this.state.display = display;
        this.state.label_v = label_v;
        this.state.label_h = label_h;
        this.state.sum_box = sum_box;
        this.state.choices = cell.choices;
        this.state.editable = editable;
        this.state.active = cell.active;
        this.state.row = cell.row;
        this.state.col = cell.col;
        this.state.remove = [];
    }

    getClasses() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
         if (this.state.sum_box) {
            classes = classes + " sum-box";
        } else {
            if (this.props.cell.choices.length === 1) {
                classes = classes + " large-num";
            }
            if (this.props.solved) {
                classes = classes + " cell-solved";
            }
            if (this.props.cell.active) {
                classes = classes + " actv";
            }
        }
        if (this.state.col === 0) {
            classes = classes + " clr";
        }
        
        return classes;
    }

    setActive() {
        if (this.state.editable) {
            this.props.onClick();
        }
    }

    render() {
        if (this.state.editable) {
            return (
                <div className={this.getClasses()} onClick={() => this.setActive()}>
                    <span className='choice-box'>{this.props.cell.choices.join('')}</span>
                </div>
            );
        }
        return (
            <div className={this.getClasses()}>
                <div className='label-v'>{this.state.label_v}</div><div className='label-h'>{this.state.label_h}</div>
            </div>
        );
    }
}
